<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Klimis\CacheMiddleware\Http\Controllers\Controller;
use Klimis\CacheMiddleware\Http\Controllers\ControllerTimeout;
use Klimis\CacheMiddleware\Http\Controllers\TestController;
use Klimis\CacheMiddleware\Middleware\CacheMiddleware;
use Orchestra\Testbench\TestCase;

class CacheMiddlewareTest extends TestCase
{

    protected $class = CacheMiddleware::class;

    protected function getEnvironmentSetUp($app)
    {
        //$app['config']->set('apikey', getenv('apikey'));
    }

    protected static function getMethod($name, $class)
    {
        $class = new \ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    public function testStringify()
    {
        $foo = self::getMethod('stringify', $this->class);
        $obj = new CacheMiddleware();
        $res = $foo->invokeArgs($obj, array(['test' => 1, 'test2' => 2]));
        $this->assertIsString($res);
    }

    public function testCacheStatus()
    {
        $controller = new TestController();
        $controller->cache = ['test'];
        $method = 'test';
        $obj = new CacheMiddleware();
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(0, $res);
    }

    public function testCacheStatusWithTimeout()
    {
        $controller = new TestController();
        $controller->cache = ['test' => 10];
        $method = 'test';
        $obj = new CacheMiddleware();
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(10, $res);
    }

    public function testCacheStatusNoCache()
    {
        $controller = new TestController();

        $method = 'test';
        $obj = new CacheMiddleware();
        $res = $obj->cacheStatus($controller, $method);
        $this->assertEquals(null, $res);
    }

    /**
     * Test adding to cache index
     */
    public function testAddRemoveKey(){
        $obj = new CacheMiddleware();
        $obj->addKey('testonly_php_unit');
    }

}
