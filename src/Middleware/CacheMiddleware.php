<?php


/**
 * Middleware for caching Controllers responses. To use controller must:
 * 1. extend from ApiController
 * 2. add methods to  cache in protected $cache = ["method1","method2"];
 * 3. Cached methods with timeout are not deleted from ALL-CACHED-KEYS-KEY !!!!!
 */

namespace Klimis\CacheMiddleware\Middleware;

use App\Traits\HelperFunctions;
use Closure;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Request;

class CacheMiddleware
{
    use HelperFunctions;

    /** Check if incoming method has cache enabled. if yes returned cached results if exists. otherwise add it to cache.
     * if cache is disabled just proceed with the response
     * @param $request
     * @param Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle($request, Closure $next)
    {
        $controller = $this->getCalledController($request);
        $method = $this->getCalledMethod($request);
        $timeout = $controller->cacheMethod($method); //timeout in seconds. if -1 then no cache

        if ($timeout >= 0) { //if method has cache proprty set
            $cacheKey = $this->keyGenerator($request, $controller);

            if (Cache::has($cacheKey)) {
                return response()->json(json_decode(Cache::get($cacheKey), true));
            }
            $response = $next($request);

            if ($response->getStatusCode() == 200) {//add only if response is 200 with data
                if($timeout === 0) {
                    Cache::forever($cacheKey, $response->getContent()); //add to cache
                }else{
                    Cache::put($cacheKey, $response->getContent(),$timeout); //add to cache
                }
                self::addKey($cacheKey); //add to main cache key used for tracking all keys
            }
        }
        $response = $next($request);
        return $response;
    }

    /** Get Controller as class
     * @param Request $request
     * @return mixed
     */
    protected function getCalledController(Request $request)
    {
        $data = explode('@', $request->route()->action['uses']);
        return new $data[0];
    }

    protected function getCalledMethod(Request $request)
    {
        $data = explode('@', $request->route()->action['uses']);
        return $data[1];
    }

    /**
     * Take controller namespace , path, and method
     * @param Request $request
     * @param $controller
     * @return string
     */
    protected function keyGenerator(Request $request, $controller)
    {
        return str_ireplace(["\\", '{', '}', '/', '"', ',', ':'], ["_", "_", '_', "_"], $controller->__toString() . $request->getPathInfo() . $this->stringify($request->all()) . $request->getMethod());
    }

    /** convert get params to json
     * @param array $array
     * @return string
     */
    protected function stringify(array $array)
    {
        return collect($array)->toJson();
    }
}
