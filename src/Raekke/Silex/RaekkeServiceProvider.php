<?php

namespace Raekke\Silex;

use JMS\Serializer\SerializerBuilder;
use Raekke\Connection;
use Raekke\Consumer;
use Raekke\Producer;
use Raekke\QueueFactory\InMemoryQueueFactory;
use Raekke\QueueFactory\QueueFactory;
use Raekke\Serializer\Serializer;
use Raekke\ServiceResolver\PimpleAwareServiceResolver;
use Silex\Application;

/**
 * @package Raekke
 */
class RaekkeServiceProvider implements \Silex\ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $app['serializer.builder'] = $app->share(function () {
            $r = new \ReflectionClass('Raekke\Connection');

            $builder = SerializerBuilder::create();
            $builder->addMetadataDir(dirname($r->getFilename()) . '/Resources/serializer', 'Raekke');

            return $builder;
        });

        $app['serializer'] = $app->share(function ($app) {
            return $app['serializer.builder']->build();
        });

        $app['raekke.serializer'] = $app->share(function ($app) {
            return new Serializer($app['serializer']);
        });

        $app['raekke.connection'] = $app->share(function ($app) {
            return new Connection($app['predis']['raekke']);
        });

        $app['raekke.queue_factory.real'] = $app->share(function ($app) {
            return new QueueFactory($app['raekke.connection'], $app['raekke.serializer']);
        });

        $app['raekke.queue_factory.in_memory'] = $app->share(function ($app) {
            return new InMemoryQueueFactory();
        });

        $app['raekke.consumer'] = $app->share(function ($app) {
            return new Consumer($app['raekke.service_resolver'], $app['raekke.queue_factory']->create('failed'));
        });

        $app['raekke.producer'] = $app->share(function ($app) {
            return new Producer($app['raekke.queue_factory']);
        });

        $app['raekke.service_resolver'] = $app->share(function ($app) {
            return new PimpleAwareServiceResolver($app);
        });

        $app['raekke.queue_factory'] = $app->raw('raekke.queue_factory.real');

        if ($app['debug']) {
            $app['raekke.queue_factory'] = $app->raw('raekke.queue_factory.in_memory');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }
}
