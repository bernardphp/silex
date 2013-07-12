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
        $this->app['bernard.serializer_symfony'] = new \stdClass;
        $this->app['bernard.serializer_jms'] = new \stdClass;

        $this->assertEquals('doctrine', $this->app['bernard.driver']);
        $this->assertEquals('naive', $this->app['bernard.serializer']);

        $this->app['bernard.serializer'] = 'jms';
        $this->assertSame($this->app['bernard.serializer_real'], $this->app['bernard.serializer_jms']);
    }
}
