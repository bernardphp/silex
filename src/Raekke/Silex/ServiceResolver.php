<?php

namespace Raekke\Silex;

use Raekke\Message\MessageInterface;
use Silex\Application;

/**
 * @package Raekke
 */
class ServiceResolver implements \Raekke\ServiceResolverInterface
{
    protected $app;
    protected $services = array();

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritDoc}
     */
    public function register($name, $serviceId)
    {
        $this->services[$name] = $serviceId;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(MessageInterface $message)
    {
        if (!isset($this->services[$message->getName()])) {
            throw new \InvalidArgumentException(sprintf('No service registered for "%s".', $message->getName()));
        }

        return $this->app[$this->services[$message->getName()]];
    }
}
