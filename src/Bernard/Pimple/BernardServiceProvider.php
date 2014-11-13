<?php

namespace Bernard\Pimple;

use Bernard\Command;
use Bernard\Consumer;
use Bernard\Driver;
use Bernard\JMSSerializer;
use Bernard\Middleware;
use Bernard\Pimple;
use Bernard\Producer;
use Bernard\QueueFactory;
use Bernard\Serializer;
use Bernard\Symfony;
use Pimple\Container;

/**
 * @package Bernard
 */
class BernardServiceProvider implements \Pimple\ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Container $app)
    {
        $this->registerDrivers($app);
        $this->registerSerializers($app);
        $this->registerConsole($app);

        $app['bernard.config'] = function ($app) {
            return $app['bernard.options'] + array(
                'driver'     => 'flat_file',
                'serializer' => 'simple',
                'prefetch'   => null,
                'directory'  => null,
            );
        };

        $app['bernard.consumer'] = function ($app) {
            return new Consumer($app['bernard.router'], $app['bernard.consumer_middleware']);
        };

        $app['bernard.producer'] = function ($app) {
            return new Producer($app['bernard.queue_factory'], $app['bernard.producer_middleware']);
        };

        $app['bernard.queue_factory'] = function ($app) {
            return new QueueFactory\PersistentFactory($app['bernard.driver'], $app['bernard.serializer']);
        };

        $app['bernard.router'] = function ($app) {
            return new Pimple\PimpleAwareRouter($app, $app['bernard.receivers']);
        };

        $app['bernard.driver'] = function ($app) {
            return $app['bernard.driver_' . $app['bernard.config']['driver']];
        };

        $app['bernard.serializer'] = function ($app) {
            return $app['bernard.serializer_' . $app['bernard.config']['serializer']];
        };

        $app['bernard.consumer_middleware'] = function ($app) {
            return new Middleware\MiddlewareBuilder;
        };

        $app['bernard.producer_middleware'] = function ($app) {
            return new Middleware\MiddlewareBuilder;
        };


        // defaults.
        foreach (array('bernard.receivers', 'bernard.options') as $default) {
            if (!isset($app[$default])) {
                $app[$default] = array();
            }
        }
    }

    /**
     * @param Container $app
     */
    protected function registerSerializers(Container $app)
    {
        $app['bernard.serializer_simple'] = function () {
            return new Serializer\SimpleSerializer;
        };

        $app['bernard.serializer_symfony'] = function ($app) {
            return new Serializer\SymfonySerializer($app['serializer']);
        };

        if (isset($app['serializer'])) {
            $app['serializer.normalizers'] = $app->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            });
        }

        $app['bernard.serializer_jms'] = function ($app) {
            return new Serializer\JMSSerializer($app['jms_serializer']);
        };

        if (isset($app['jms_serializer'])) {
            $app['jms_serializer.builder'] = $app->extend('jms_serializer.builder', function ($builder, $app) {
                $builder->configureHandlers(function ($registry) {
                    $registry->registerSubscribingHandler(new JMSSerializer\EnvelopeHandler);
                });
            });
        }

        if (isset($app['serializer.normalizers'])) {
            $app['serializer.normalizers'] = $app->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            });
        }
    }

    /**
     * @param Container $app
     */
    protected function registerDrivers(Container $app)
    {
        $app['bernard.driver_predis'] = function ($app) {
            return new Driver\PredisDriver($app['predis']);
        };

        $app['bernard.driver_flat_file'] = function ($app) {
            return new Driver\FlatFileDriver($app['bernard.config']['directory']);
        };

        $app['bernard.driver_doctrine'] = function ($app) {
            return new Driver\DoctrineDriver($app['dbs']['bernard']);
        };

        $app['bernard.driver_redis'] = function ($app) {
            return new Driver\PhpRedisDriver($app['redis']);
        };

        $app['bernard.driver_iron_mq'] = function ($app) {
            return new Driver\IronMqDriver($app['iron_mq'], $app['bernard.config']['prefetch']);
        };

        $app['bernard.driver_sqs'] = function ($app) {
            return new Driver\SqsDriver($app['aws']->get('sqs'), $app['bernard.queue_urls'], $app['bernard.config']['prefetch']);
        };
    }

    /**
     * @param Container $app
     */
    protected function registerConsole(Container $app)
    {
        if (!isset($app['console'])) {
            return;
        }

        $app['console'] = $app->extend('console', function ($console, $app) {
            $console->add(new Command\ConsumeCommand($app['bernard.consumer'], $app['bernard.queue_factory']));
            $console->add(new Command\ProduceCommand($app['bernard.producer']));

            return $console;
        });
    }
}
