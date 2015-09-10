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
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;

/**
 * @package Bernard
 */
class BernardServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritDoc}
     */
    public function register(Container $container)
    {
        $this->registerDrivers($container);
        $this->registerSerializers($container);
        $this->registerConsole($container);

        $container['bernard.config'] = function ($container) {
            return $container['bernard.options'] + array(
                'driver'     => 'flat_file',
                'serializer' => 'simple',
                'prefetch'   => null,
                'directory'  => null,
            );
        };

        $container['bernard.consumer'] = function ($container) {
            return new Consumer($container['bernard.router'], $container['bernard.consumer_middleware']);
        };

        $container['bernard.producer'] = function ($container) {
            return new Producer($container['bernard.queue_factory'], $container['bernard.producer_middleware']);
        };

        $container['bernard.queue_factory'] = function ($container) {
            return new QueueFactory\PersistentFactory($container['bernard.driver'], $container['bernard.serializer']);
        };

        $container['bernard.router'] = function ($container) {
            return new Pimple\PimpleAwareRouter($container, $container['bernard.receivers']);
        };

        $container['bernard.driver'] = function ($container) {
            return $container['bernard.driver_' . $container['bernard.config']['driver']];
        };

        $container['bernard.serializer'] = function ($container) {
            return $container['bernard.serializer_' . $container['bernard.config']['serializer']];
        };

        $container['bernard.consumer_middleware'] = function ($container) {
            return new Middleware\MiddlewareBuilder;
        };

        $container['bernard.producer_middleware'] = function ($container) {
            return new Middleware\MiddlewareBuilder;
        };

        // defaults.
        foreach (array('bernard.receivers', 'bernard.options') as $default) {
            if (!isset($container[$default])) {
                $container[$default] = array();
            }
        }
    }

    /**
     * @param Container $container
     */
    protected function registerSerializers(Container $container)
    {
        $container['bernard.serializer_simple'] = function () {
            return new Serializer\SimpleSerializer;
        };

        $container['bernard.serializer_symfony'] = function ($container) {
            return new Serializer\SymfonySerializer($container['serializer']);
        };

        if (isset($container['serializer'])) {
            $container['serializer.normalizers'] = $container->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            });
        }

        $container['bernard.serializer_jms'] = function ($container) {
            return new Serializer\JMSSerializer($container['jms_serializer']);
        };

        if (isset($container['jms_serializer'])) {
            $container['jms_serializer.builder'] = $container->extend('jms_serializer.builder', function ($builder, $container) {
                $builder->configureHandlers(function ($registry) {
                    $register->registerSubscribingHandler(new JMSSerializer\EnvelopeHandler);
                });
            });
        }

        if (isset($container['serializer.normalizers'])) {
            $container['serializer.normalizers'] = $container->extend('serializer.normalizers', function ($normalizers) {
                array_unshift($normalizers, new Symfony\EnvelopeNormalizer, new Symfony\DefaultMessageNormalizer);

                return $normalizers;
            });
        }
    }

    /**
     * @param Container $container
     */
    protected function registerDrivers(Container $container)
    {
        $container['bernard.driver_predis'] = function ($container) {
            return new Driver\PredisDriver($container['predis']);
        };

        $container['bernard.driver_flat_file'] = function ($container) {
            return new Driver\FlatFileDriver($container['bernard.config']['directory']);
        };

        $container['bernard.driver_doctrine'] = function ($container) {
            return new Driver\DoctrineDriver($container['dbs']['bernard']);
        };

        $container['bernard.driver_redis'] = function ($container) {
            return new Driver\PhpRedisDriver($container['redis']);
        };

        $container['bernard.driver_iron_mq'] = function ($container) {
            return new Driver\IronMqDriver($container['iron_mq'], $container['bernard.config']['prefetch']);
        };

        $container['bernard.driver_sqs'] = function ($container) {
            return new Driver\SqsDriver($container['aws']->get('sqs'), $container['bernard.queue_urls'], $container['bernard.config']['prefetch']);
        };
    }

    /**
     * @param Container $container
     */
    protected function registerConsole(Container $container)
    {
        if (!isset($container['console'])) {
            return;
        }

        $container['console'] = $container->extend('console', function ($console, $container) {
            $console->add(new Command\ConsumeCommand($container['bernard.consumer'], $container['bernard.queue_factory']));
            $console->add(new Command\ProduceCommand($container['bernard.producer']));

            return $console;
        });
    }
}
