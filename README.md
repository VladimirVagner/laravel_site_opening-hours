# Openinghours

Platform to manager opening hours for services and their channels.

[![Build Status][ico-travis]][link-travis]
[![Maintainability][ico-maintainability]][link-maintainability]
[![Test Coverage][ico-test-coverage]][link-test-coverage]

## Installation

### Create config file

Copy the .env.example to .env and

- Fill in the `MySQL` (or MariaDb) database credentials.
- Fill in the `VESTA API` configuration (make sure it's not base64 encoded).
- Fill in the `VESTA_SOURCE_URL` for the links to vesta services (use a 
  `${identifier}` placeholder).
- Fill in the Queue driver, for production environments use redis, beanstalkd or
  SQS. DO NOT use sync as a queue, rather use database in testing environments
  (`https://laravel.com/docs/5.4/queues`).
- Fill in the base URI that is used to build the LOD version of the openinghours
  triple (`BASE_URI`).
- Fill in the base URI that is used to link to a data representation of
  something (`DATA_REPRESENTATION_URI`).
- Fill in the `SPARQL` configuration to read data from.
- Fill in the `SPARQL` configuration to write data to, don't forget the name of
  the graph and the specific endpoint of the sparql-graph-crud-auth, which is
  used to write (possibly) larger amounts of triples to.
- Mails are sent through SMTP.
- Set session driver to database (anything but file).
- Set 1 worker in supervisor
  (`https://laravel.com/docs/5.4/queues#supervisor-configuration`) to handle the
  writes to the different systems (VESTA, LOD). Only using 1 will make sure no
  dirty read/writes will happen to the SPARQL endpoint, you can use supervisor
  or a custom system that keeps a Laravel worker up and running. Make sure that
  the worker is restarted after an update on the software 
  (`php artisan queue:restart`).

### Build the back & front-end

Build the back & front-end by running following commands:

```bash
composer install
./artisan migrate
./artisan db:seed
./artisan passport:keys
npm install
gulp build
```

The `./artisan db:seed` command will generate an admin user with a random
generated password that's outputted to the command line, the default email is
admin@foo.bar.

## Maintenance and deployment

When updating the software the commands you'll want to run are the following:

```bash
composer install
composer update
gulp build
php artisan migrate
php artisan cache:clear
php artisan queue:restart
```
    
> Caveat: Keep an eye out for the johngrogg/ics-parser releases, currently this 
> dependency is set to dev-master, because the branch contains an implementation
> that we use in order to provide correct API responses. This is an actively
> maintained library so (patch) releases are in play.

## Fetch services

In order to fetch a list of services from the SPARQL endpoint you'll need to
configure the SPARQL endpoint (READ). You can then run the following command:

```bash
php artisan openinghours:fetch-services
```

This will fill the services table with the identifiers, labels of the available
services from the SPARQL endpoint.

## Fetch recreatex

In order to add openinghours from the Recreatex application to services that are
present in the Recreatex application, you can command below, after configuring
the Recreatex variables in the .env file.

This command will fetch services that are available in the application and have
a recreatex source, a property fetched during the retrieval of services from the
SPARQL endpoint using the fetch-services command.

The recreatex command will fetch services using the configured recreatex
endpoint and shop-id and will do so, year by year, currently configured to fetch
data from 2017 until 2020. These events will be added to the channel
"Infrastructuur".

Because Recreatex doesn't save it's events in a calendar standard, rather it
saves every single day as an array of 2 events (maximum).

This would result in 365 events per channel, which is far from optimal to work
with without additional tweaking, therefore a small algorithm was written that
parses weekly RRULEs from the yearly event list.

```bash
php artisan openinghours:fetch-recreatex
```

> **Note**: this will import openinghours from 2017 up until 2020, make sure you
> refresh the application in the browser after fetching the recreatex
> openinghours, because the front-end caches responses by default.

## Email

Email is now done through SMTP

## VESTA

Output can be written to VESTA on a weekly basis by using a predefined command.
This will write a weekschedule to VESTA starting on monday of the week that the
timestamp of execution falls in. e.g. If it's called friday at 5PM, the output
will contain the schedule monday-sunday of that week that friday falls into.
Scheduling it monday mornings will produce a schedule for that entire week.

```bash
php artisan openinghours:update-vesta
```

## Usage

Queries - No APIB or Swagger available.

- The serviceUri parameter is required and must be the URI of a service.
- The channel parameter is optional and must be the name of a specific channel
  within the given service.
- The format is optional and by default will return a JSON result, other formats
  can be: html, text, json-ld.

### Get the schedule for the next 7 days

The URI template to get the openinghours for a certain service and a channel
within that service (optional).

If no channel is passed, all channels will be returned with a schedule of the
coming 7 days.

- `{host}/api/query?q=week&serviceUri={serviceUri}&channel={channel}&format={format}`

### Get a week schedule starting on monday

Passing a date is optional and if passed will return the monday-sunday schedule
of the week that the date falls in.

- `{host}/api/query?q=fullWeek&serviceUri={serviceUri}&channel={channel}&date=dd-mm-yyyy&format={format}`

### Is something open right now?

- `{host}/api/query?q=now&serviceUri={serviceUri}&channel={channel}&format={format}`

### Get the openinghours for a specific day

- `{host}/api/query?q=day&date={mm-dd-yyyy}&serviceUri={serviceUri}&channel={channel}&format={format}`

## Contributions

The first iteration codebase has been written by 
[weconnectdata](https://github.com/weconnectdata), more specifically by
[@thgh](https://github.com/thgh) and [@coreation](https://github.com/coreation).

The design and functional analysis has been performed by
[@mietcls](https://github.com/mietcls)

Codeclimate set-up, code review and further maintenance will be done by the
Digipolis team.

[ico-travis]: https://img.shields.io/travis/StadGent/laravel_site_opening-hours/develop.svg?style=flat-square
[ico-maintainability]: https://img.shields.io/codeclimate/maintainability/StadGent/laravel_site_opening-hours.svg?style=flat-square
[ico-test-coverage]: https://img.shields.io/codeclimate/c/StadGent/laravel_site_opening-hours.svg?style=flat-square

[link-travis]: https://travis-ci.org/StadGent/laravel_site_opening-hours
[link-maintainability]: https://codeclimate.com/github/StadGent/laravel_site_opening-hours/maintainability
[link-test-coverage]: https://codeclimate.com/github/StadGent/laravel_site_opening-hours/test_coverage
