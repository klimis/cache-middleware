<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testStringify()
    {
        $foo = self::getMethod('stringify', $this->class);
        $obj = new CacheMiddleware();
        $res = $foo->invokeArgs($obj, array(['test' => 1, 'test2' => 2]));
        $this->assertIsString($res);
    }

    public function testAddKey(){

    }

}
