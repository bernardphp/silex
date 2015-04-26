<?php

namespace Bernard\Tests\Pimple;

use Bernard\Pimple\BernardServiceProvider;
use Pimple\Container;

class BernardServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->app = new Container;
        $this->app->register(new BernardServiceProvider);
    }

    public function testDefaultOptions()
    {
        // Default settings
        $this->assertEquals('flat_file', $this->app['bernard.config']['driver']);
        $this->assertEquals(null, $this->app['bernard.config']['prefetch']);
        $this->assertEquals('simple', $this->app['bernard.config']['serializer']);
    }

    public function testOverrideOptions()
    {
        $this->app['bernard.options'] = array(
            'driver' => 'predis',
            'serializer' => 'symfony',
            'prefetch' => 10,
        );

        $this->assertEquals(10, $this->app['bernard.config']['prefetch']);
        $this->assertEquals('predis', $this->app['bernard.config']['driver']);
        $this->assertEquals('symfony', $this->app['bernard.config']['serializer']);
    }
}
