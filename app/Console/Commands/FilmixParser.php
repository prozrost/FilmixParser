<?php

namespace App\Console\Commands;

use App\Actor;
use Goutte\Client;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\DomCrawler\Crawler;

class FilmixParser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing-filmix {requiredNewActors=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parsing actors from website Filmix.cc';

    public $logger;
    public $startingPage;
    public $filmixPersonsUrl;
    public $newActorsCount;

    /**
     * Create a new command instance.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger('ParsingFilmixLogger');
        $this->logger->pushHandler(new StreamHandler(storage_path("logs/parsing-filmix.log"), Logger::INFO));
        $this->startingPage = 1;
        $this->filmixPersonsUrl = "http://filmix.cc/persons/page/";
        $this->newActorsCount = 0;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->filmixPersonsUrl . $this->startingPage);

        $this->parseActors($crawler, $client);
    }

    private function parseActors(Crawler $crawler, Client $client, $parsedPersonCount = 0)
    {
        $requiredNewActors = $this->argument('requiredNewActors');

        $crawler->filter(".shortstory")->each(function ($shortstory, $position) use ($client, $parsedPersonCount, $requiredNewActors) {
            $actorData = $this->getActorData($shortstory, $client);

            $localActor = Actor::where('origin_name', $actorData['origin_name'])->first();
            if (!$localActor) {
                Actor::create($actorData);
                $this->newActorsCount++;
                $this->logActorInfo($actorData);
            }

            if ($this->newActorsCount == $requiredNewActors) {
                exit();
            }
        });
            if ($this->newActorsCount < $requiredNewActors) {
                $this->startingPage++;
                $crawler = $client->request('GET', $this->filmixUrl . $this->startingPage);
                $this->parseActors($crawler, $client, $parsedPersonCount);
            }
    }

    private function getActorData(Crawler $shortstoryCrawler, Client $client) : array
    {
        $actorLink = $shortstoryCrawler->filter(".name > a")->attr('href');
        $dateOfBirth = $shortstoryCrawler->filter('.personebirth')->text();

        $actorData = [
            'translated_name' => $shortstoryCrawler->filter(".name")->text(),
            'origin_name' => $shortstoryCrawler->filter(".origin-name")->text(),
            'person_link' => $actorLink,
            'poster_url' => $shortstoryCrawler->filter('.poster-box > img')->attr('src'),
            'date_of_birth' =>  $this->getDateFromString($dateOfBirth),
            'place_of_birth' => $shortstoryCrawler->filter('.full > .item')
                ->eq(1)
                ->filter('span')
                ->eq(1)
                ->text()
        ];
        foreach ($actorData as $actorDataField) {
            strip_tags($actorDataField);
        }

        $personInfoCrawler = $client->request('GET', $actorLink);
        try {
            $biography = $personInfoCrawler->filter(".about")->text();
            $actorData = array_merge($actorData, ['additional_info' => json_encode(
                ['biography' => $this->satitizeBiography($biography)],  JSON_UNESCAPED_UNICODE)
            ]);
            return $actorData;
        } catch (\InvalidArgumentException $exception) {
            return $actorData;
        }
    }

    private function logActorInfo(array $actorData)
    {
        $this->logger->addInfo("Actor parsed at: ". now()->toDateTimeString());
        $this->logger->addInfo("Actor origin name: {$actorData['origin_name']}");
    }

    private function getDateFromString($stringDate) {
        $stringDate = substr($stringDate, 0, strpos($stringDate, ','));

        $russianMonths = [
            'января',
            'фервраля',
            'марта',
            'апреля',
            'мая',
            'июня',
            'июля',
            'августа',
            'сентября',
            'октября',
            'ноября',
            'декабря'
        ];
        $englishMonths = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December'
        ];

        $date = str_replace($russianMonths, $englishMonths, $stringDate);
        return date("Y-m-d", strtotime($date));
    }

    private function satitizeBiography($biography)
    {
        $biography = strip_tags($biography);
        $biography = str_replace(["\r", "\n"], "", $biography);
        return $biography;
    }
}
