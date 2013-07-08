<?php

namespace Bernard\Silex;

use Bernard\Consumer;
use Bernard\Driver;
use Bernard\JMSSerializer;
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
        $app['bernard.driver'] = 'doctrine';
        $app['bernard.serializer'] = 'symfony';

        $this->registerDrivers($app);
        $this->registerSerializers($app);
        $this->registerConsole($app);

        $app['bernard.consumer'] = $app->share(function ($app) {
            return new Consumer($app['bernard.service_resolver'], $app['bernard.queue_factory']);
        });

        $app['bernard.producer'] = $app->share(function ($app) {
            return new Producer($app['bernard.queue_factory']);
        });

        $app['bernard.queue_factory'] = $app->share(function ($app) {
            return new QueueFactory\PersistentFactory($app['bernard.driver'], $app['bernard.serializer']);
        });

        $app['bernard.service_resolver'] = $app->share(function ($app) {
            $resolver = new Pimple\PimpleAwareResolver($app);

            $names = array_keys($app['bernard.services']);
            $serviceIds = array_values($app['bernard.services']);

            array_map(array($resolver, 'register'), $names, $serviceIds);

            return $resolver;
        });

        $app['bernard.driver_real'] = $app->share(function ($app) {
            return $app['bernard.driver_' . $app['bernard.driver']];
        });

        $app['bernard.serializer_real'] = $app->share(function ($app) {
            return $app['bernard.serializer_' . $app['bernard.serializer']];
        });
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
            return new PredisDriver($app['predis']);
        });

        $app['bernard.driver_doctrine'] = $app->share(function ($app) {
            return new Driver\DoctrineDriver($app['dbs']['bernard']);
        });

        $app['bernard.driver_redis'] = $app->share(function ($app) {
            return new Driver\RedisDriver($app['redis']);
        });

        $app['bernard.driver_iron_mq'] = $app->share(function ($app) {
            return new Driver\IronMqDriver($app['iron_mq']);
        });

        $app['bernard.driver_sqs'] = $app->share(function ($app) {
            return new Driver\SqsDriver($app['aws']->get('sqs'));
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
            $app['console']->add(new Symfony\ConsumeCommand($app['bernard.consumer']));
        }));
    }
}
