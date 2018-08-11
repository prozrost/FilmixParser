# Parsing Filmix

Parsing actors from filmix.cc and stored them to local db.

## Getting Started
```
 composer install
```
```
 cp .env.example .env
```
 Change DB settings in .env file
```
 php artisan key:generate
```
```
 php artisan migrate
```
```
 php artisan store-filmix-actors {numberOfActors=100}
```

_numberOfActors_ - required Actors to parse, **default** 100.


