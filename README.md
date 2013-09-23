Bernard bindings for Silex
==========================

[![Build Status](https://travis-ci.org/bernardphp/silex.png?branch=master)](https://travis-ci.org/bernardphp/silex)

Brings Bernard to Silex.

Getting Started
---------------

Add the requirement to your `composer.json` file and register it with you Application.

``` json
{
    "require" : {
        "bernard/silex" :  "~0.4@dev"
    }
}
```

``` php
<?php

$app = new Silex\Application;
$app->register(new Bernard\Silex\BernardServiceProvider, array(
    'bernard.options' => array(
        'driver' => 'doctrine', // or redis, predis, sqs, iron_mq
        'serializer' => 'symfony', // or jms or simple
    ),
));
```

After that you have to make a decision about what driver and what kind of Serializer
you want to use.

The following serializers are supported:

 * Simple. No dependencies and it is the default.
 * JMS Serializer. Requires a service with id `jms_serializer` and `jms_serializer.builder` is present.
 * Symfony Serializer. Requires `SerializerServiceProvider` is registered before this provider.


The following drivers are supported:

 * Doctrine DBAL requires `DoctrineServiceProvider` where it try and use a `bernard` connection.
 * Predis requires https://github.com/nrk/PredisServiceProvider and a `predis` service. If you use the multi
 service provider, you should overwrite `bernard.predis_driver` and do a custom service.
 * Redis extension. Requires http://pecl.php.net/package/redis to be installed and a `redis` service.
 * Amazon SQS requires AWS SDK PHP version 2 or creater and https://github.com/aws/aws-sdk-php-silex.
 * Iron.MQ requires `iron-io/iron_mq` package and a `iron_mq` service.

Registering with the ServiceResolver
------------------------------------

The ServiceResolver enabled supports service ids. This means they are lazy loaded when they are needed instead
of when they are registering.

Register `bernard.services` with an array of `MessageName => ServiceId` like so:

``` php
<?php

$app['bernard.receivers'] = array(
    'ImportUsers' => 'users_worker',
);
```

Console
-------

If there is a service named `console` the consume command will be automatically registred. For advanced
usecases see the official documentation on Bernard.
