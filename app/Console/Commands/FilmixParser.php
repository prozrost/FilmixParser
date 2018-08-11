<?php

namespace App\Console\Commands;

use App\Actor;
use Goutte\Client;
use Illuminate\Console\Command;
use Monolog\Logger;
use Symfony\Component\DomCrawler\Crawler;

class FilmixParser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parsing-filmix {actorsCount=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parsing actors from website Filmix.cc';

    public $logger;
    public $startingPage;
    public $filmixUrl;
    public $parsedPersonCount;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger('ParsingFilmixLogger');
        $this->startingPage = 1;
        $this->filmixUrl = "http://filmix.cc/persons/page/";
        $this->parsedPersonCount = 0;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $crawler = $client->request('GET', $this->filmixUrl . $this->startingPage);

        $this->parsePage($crawler, $client);
    }

    private function parsePage(Crawler $crawler, Client $client, $parsedPersonCount = 0)
    {
        $actorsCount = $this->argument('actorsCount');

        $crawler->filter(".shortstory")->each(function ($shortstory, $position) use ($client, $parsedPersonCount, $actorsCount) {
            $actorData = $this->getActorData($shortstory, $client);
            Actor::create($actorData);
            $this->parsedPersonCount++;
            $this->logger->addInfo("Parsed Person#{$this->parsedPersonCount}");
            $this->logger->addInfo("Position: {$position}");
            if ($this->parsedPersonCount == $actorsCount) {
                exit();
            }
        });
            if ($this->parsedPersonCount < $actorsCount) {
                $this->startingPage++;
                $crawler = $client->request('GET', $this->filmixUrl . $this->startingPage);
                $this->parsePage($crawler, $client, $parsedPersonCount);
            }
    }

    private function getActorData(Crawler $shortstoryCrawler, Client $client) : array
    {
        $actorLink = $shortstoryCrawler->filter(".name > a")->attr('href');

        $actorData = [
            'translated_name' => $shortstoryCrawler->filter(".name")->text(),
            'origin_name' => $shortstoryCrawler->filter(".origin-name")->text(),
            'person_link' => $actorLink,
            'poster_url' => $shortstoryCrawler->filter('.poster-box > img')->attr('src'),
            'date_of_birth' =>  $shortstoryCrawler->filter('.personebirth')->text(),
            'place_of_birth' => $shortstoryCrawler->filter('.full > .item')
                ->eq(1)
                ->filter('span')
                ->eq(1)
                ->text()
        ];

        $personInfoCrawler = $client->request('GET', $actorLink);
        try {
            $additionalInfo = $personInfoCrawler->filter(".about")->text();
            $actorData = array_merge($actorData, ['biography' =>  json_encode($additionalInfo)]);
            return $actorData;
        } catch (\InvalidArgumentException $exception) {
            return $actorData;
        }
    }
}
