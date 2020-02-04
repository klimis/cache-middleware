<?php
/**
 * TODO:: DELETE EXPIRED KEYS FROM $indexKey
 */

/**
 * Middleware for caching Controllers responses. To use controller must:
 * 1. extend from ApiController
 * 2. add methods to  cache in protected $cache = ["method1","method2"];
 * 3. Cached methods with timeout are not deleted from ALL-CACHED-KEYS-KEY !!!!!
 */

namespace Klimis\CacheMiddleware\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Request;

class CacheMiddleware
{
    protected static $indexKey = 'ALL-CACHED-KEYS-KEY'; // master main index key. we keep references here for all keys

    /** Check if incoming method has cache enabled. if yes returned cached results if exists. otherwise add it to cache.
     * if cache is disabled just proceed with the response
     * @param $request
     * @param Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle($request, Closure $next)
    {
        $controller = $this->getCalledController($request); // get the calling contoller
        $method = $this->getCalledMethod($request); // get the  method of the controller
        $timeout = $this->cacheStatus($controller, $method); //timeout in seconds. if null then no cache

        if (is_numeric($timeout)) { //if method has cache property set
            $cacheKey = $this->keyGenerator($request, $controller);
            if (Cache::has($cacheKey)) { //return if it exists in cache
                return response()->json(json_decode(Cache::get($cacheKey), true));
            }
            $response = $next($request);
            if ($response->getStatusCode() == 200) {//add only if response is 200 with data
                if ($timeout === 0) {
                    Cache::forever($cacheKey, $response->getContent()); // add to cache forever
                } else {
                    Cache::put($cacheKey, $response->getContent(), $timeout); //add to cache with timeout
                }
                $this->addKey($cacheKey); //add to main cache key used for tracking all keys
            }
        }
        $response = $next($request);
        return $response;
    }

    /*** Cache Status of method.
     * > 0 : timeout defined
     * 0 :   forever
     * null : no cache
     * @param Controller $controller
     * @param string $method
     * @return int|null
     */
    public function cacheStatus(Controller $controller, string $method): ?int
    {
        $cache = null; //no cache
        if (property_exists($controller, 'cache')) {
            if (isset($controller->cache[$method])) {
                $cache = $controller->cache[$method]; //if timeout isset timeout time in seconds
            } elseif (in_array($method, $controller->cache)) { //if not timeout isset return 0
                $cache = 0;
            }
        }
        return $cache;
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
    protected function keyGenerator(Request $request, Controller $controller)
    {
        return str_ireplace(["\\", '{', '}', '/', '"', ',', ':'], ["_", "_", '_', "_"], get_class($controller) . $request->getPathInfo() . $this->stringify($request->all()) . $request->getMethod());
    }

    /** convert get params to json
     * @param array $array
     * @return string
     */
    protected function stringify(array $array)
    {
        return collect($array)->toJson();
    }

    /** Create key if not exists in reference cache key
     * @param string $key
     */
    public function addKey(string $key)
    {
        $keys = $this->getAllKeys();
        if (is_array($keys) && !in_array($key, $keys)) {
            array_push($keys, $key);
        }
        Cache::put(self::$indexKey, implode('####', $keys));
    }

    /** Get all keys
     * @return array
     */
    public function getAllKeys()
    {
        $keysString = Cache::get(self::$indexKey);
        if ($keysString && (strpos($keysString, '####') !== false)) {
            $allKeys = explode('####', $keysString);
        } else {
            $allKeys[] = $keysString;
        }
        return array_filter($allKeys);
    }

    /** Remove key from index  key
     * @param string $keytodel
     */
    public function removeKey(string $keytodel)
    {
        $keys = $this->getAllKeys();
        if (($key = array_search($keytodel, $keys)) !== false) {
            unset($keys[$key]);
        }
        Cache::put(self::$indexKey, implode('####', $keys));
    }
}
