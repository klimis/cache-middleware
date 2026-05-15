<?php

declare(strict_types=1);

namespace Tests\Unit;

use Klimis\CacheMiddleware\Http\Controllers\TestController;
use Klimis\CacheMiddleware\Middleware\CacheMiddleware;
use Orchestra\Testbench\TestCase;

class CacheMiddlewareTest extends TestCase
{
    protected $class = CacheMiddleware::class;

    protected function getEnvironmentSetUp($app)
    {
        // $app['config']->set('apikey', getenv('apikey'));
    }

    protected static function getMethod($name, $class)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    public function test_stringify()
    {
        $foo = self::getMethod('stringify', $this->class);
        $obj = new CacheMiddleware;
        $res = $foo->invokeArgs($obj, [['test' => 1, 'test2' => 2]]);
        $this->assertIsString($res);
    }

    public function test_cache_status()
    {
        $controller = new TestController;
        $controller->cache = ['test'];
        $method = 'test';
        $obj = new CacheMiddleware;
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(0, $res);
    }

    public function test_cache_status_with_timeout()
    {
        $controller = new TestController;
        $controller->cache = ['test' => 10];
        $method = 'test';
        $obj = new CacheMiddleware;
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(10, $res);
    }

    public function test_cache_status_no_cache()
    {
        $controller = new TestController;

        $method = 'test';
        $obj = new CacheMiddleware;
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(null, $res);
    }
}
