<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase;

class CacheMiddlewareTest extends TestCase
{

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('apikey', getenv('apikey'));

    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
    }
}
