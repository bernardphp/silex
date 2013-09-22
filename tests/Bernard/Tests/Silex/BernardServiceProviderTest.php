<?php

namespace Bernard\Tests\Silex;

use Bernard\Silex\BernardServiceProvider;
use Silex\Application;

class BernardServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->app = new Application;
        $this->app->register(new BernardServiceProvider);
    }

    public function testDefaultOptions()
    {
        // Default settings
        $this->assertEquals('doctrine', $this->app['bernard.config']['driver']);
        $this->assertEquals('naive', $this->app['bernard.config']['serializer']);
    }

    public function testOverrideOptions()
    {
        $this->app['bernard.options'] = array(
            'driver' => 'predis',
            'serializer' => 'symfony',
        );

        $this->assertEquals('predis', $this->app['bernard.config']['driver']);
        $this->assertEquals('symfony', $this->app['bernard.config']['serializer']);
    }
}
