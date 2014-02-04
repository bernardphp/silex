<?php

namespace Bernard\Silex;

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
use Silex\Application;

/**
 * @package Bernard
 */
class BernardServiceProvider implements \Silex\ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $this->registerDrivers($app);
        $this->registerSerializers($app);
        $this->registerConsole($app);

        $app['bernard.config'] = $app->share(function ($app) {
            return $app['bernard.options'] + array(
                'driver'     => 'flat_file',
                'serializer' => 'simple',
                'prefetch'   => null,
                'directory'  => null,
            );
        });

        $app['bernard.consumer'] = $app->share(function ($app) {
            return new Consumer($app['bernard.router'], $app['bernard.consumer_middleware']);
        });

        $app['bernard.producer'] = $app->share(function ($app) {
            return new Producer($app['bernard.queue_factory'], $app['bernard.producer_middleware']);
        });

        $app['bernard.queue_factory'] = $app->share(function ($app) {
            return new QueueFactory\PersistentFactory($app['bernard.driver'], $app['bernard.serializer']);
        });

        $app['bernard.router'] = $app->share(function ($app) {
            return new Pimple\PimpleAwareRouter($app, $app['bernard.receivers']);
        });

        $app['bernard.driver'] = $app->share(function ($app) {
            return $app['bernard.driver_' . $app['bernard.config']['driver']];
        });

        $app['bernard.serializer'] = $app->share(function ($app) {
            return $app['bernard.serializer_' . $app['bernard.config']['serializer']];
        });

        $app['bernard.consumer_middleware'] = $app->share(function ($app) {
            return new Middleware\MiddlewareBuilder;
        });

        $app['bernard.producer_middleware'] = $app->share(function ($app) {
            return new Middleware\MiddlewareBuilder;
        });


        // defaults.
        foreach (array('bernard.receivers', 'bernard.options') as $default) {
            if (!isset($app[$default])) {
                $app[$default] = array();
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }

    /**
     * @param Application $app
     */
    protected function registerSerializers(Application $app)
    {
        $app['bernard.serializer_simple'] = $app->share(function () {
            return new Serializer\SimpleSerializer;
        });

        $app['bernard.serializer_symfony'] = $app->share(function ($app) {
            return new Serializer\SymfonySerializer($app['serializer']);
        });

        if (isset($app['serializer'])) {
            $app['serializer.normalizers'] = $app->share($app->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            }));
        }

        $app['bernard.serializer_jms'] = $app->share(function ($app) {
            return new Serializer\JMSSerializer($app['jms_serializer']);
        });

        if (isset($app['jms_serializer'])) {
            $app['jms_serializer.builder'] = $app->share($app->extend('jms_serializer.builder', function ($builder, $app) {
                $builder->configureHandlers(function ($registry) {
                    $register->registerSubscribingHandler(new JMSSerializer\EnvelopeHandler);
                });
            }));
        }

        if (isset($app['serializer.normalizers'])) {
            $app['serializer.normalizers'] = $app->share($app->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            }));
        }
    }

    /**
     * @param Application $app
     */
    protected function registerDrivers(Application $app)
    {
        $app['bernard.driver_predis'] = $app->share(function ($app) {
            return new Driver\PredisDriver($app['predis']);
        });

        $app['bernard.driver_flat_file'] = $app->share(function ($app) {
            return new Driver\FlatFileDriver($app['bernard.config']['directory']);
        });

        $app['bernard.driver_doctrine'] = $app->share(function ($app) {
            return new Driver\DoctrineDriver($app['dbs']['bernard']);
        });

        $app['bernard.driver_redis'] = $app->share(function ($app) {
            return new Driver\PhpRedisDriver($app['redis']);
        });

        $app['bernard.driver_iron_mq'] = $app->share(function ($app) {
            return new Driver\IronMqDriver($app['iron_mq'], $app['bernard.config']['prefetch']);
        });

        $app['bernard.driver_sqs'] = $app->share(function ($app) {
            return new Driver\SqsDriver($app['aws']->get('sqs'), $app['bernard.queue_urls'], $app['bernard.config']['prefetch']);
        });
    }

    /**
     * @param Application $app
     */
    protected function registerConsole(Application $app)
    {
        if (!isset($app['console'])) {
            return;
        }

        $app['console'] = $app->share($app->extend('console', function ($console, $app) {
            $console->add(new Command\ConsumeCommand($app['bernard.consumer'], $app['bernard.queue_factory']));
            $console->add(new Command\ProduceCommand($app['bernard.producer']));

            return $console;
        }));
    }
}
