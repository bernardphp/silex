<?php

namespace Raekke\Silex;

use JMS\Serializer\SerializerBuilder;
use Raekke\Connection\PredisConnection;
use Raekke\Consumer;
use Raekke\Producer;
use Raekke\QueueFactory\InMemoryFactory;
use Raekke\QueueFactory\PersistentFactory;
use Raekke\Serializer\JMSSerializer;
use Raekke\ServiceResolver\PimpleAwareResolver;
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
            return new JMSSerializer($app['serializer']);
        });

        $app['raekke.predis'] = $app->share(function ($app) {
            return $app['predis'];
        });

        $app['raekke.connection'] = $app->share(function ($app) {
            return new PredisConnection($app['raekke.predis']);
        });

        $app['raekke.queue_factory.real'] = $app->share(function ($app) {
            return new PersistentFactory($app['raekke.connection'], $app['raekke.serializer']);
        });

        $app['raekke.queue_factory.in_memory'] = $app->share(function ($app) {
            return new InMemoryFactory();
        });

        $app['raekke.consumer'] = $app->share(function ($app) {
            return new Consumer($app['raekke.service_resolver'], $app['raekke.queue_factory']->create('failed'));
        });

        $app['raekke.producer'] = $app->share(function ($app) {
            return new Producer($app['raekke.queue_factory']);
        });

        $app['raekke.service_resolver'] = $app->share(function ($app) {
            return new PimpleAwareResolver($app);
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
