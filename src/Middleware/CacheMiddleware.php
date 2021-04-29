<?php
/**
 * TODO:: DELETE EXPIRED KEYS FROM $indexKey
 */

/**
 * Middleware for caching Controllers responses. To use controller must:
 * 1. extend from ApiController
 * 2. add methods to  cache in protected $cache = ["method1","method2"];
 * 3. with timeout public  $cache = [
'methodA' => 10
];
 *  4. setting('global.enable_global_cache'). Enable globally if cache middleware exists for method
 * 5. IMPORTANT: Cached methods with timeout are not deleted from ALL-CACHED-KEYS-KEY !!!!!
 */

namespace Klimis\CacheMiddleware\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Request;

class CacheMiddleware
{
    CONST INDEXKEY = 'ALL-CACHED-KEYS-KEY'; // master main index key. we keep references here for all keys
    CONST NOCACHEHEADER = 'Api-Disable-Cache'; //Set this header to 0 avoid caching

    /** Check if incoming method has cache enabled. if yes returned cached results if exists. otherwise add it to cache.
     * if cache is disabled just proceed with the response
     * @param $request
     * @param Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle($request, Closure $next)
    {
        $controller = $this->getCalledController($request); // Get the calling contoller
        $method = $this->getCalledMethod($request); // Get the  method of the controller
        $cacheStatus = $this->cacheStatus($controller, $method); // Timeout in seconds. if null then no cache

        if (is_numeric($cacheStatus) && !$this->noCacheRequest() && !env('DISABLE_CACHE')) { // If method has cache property set
            $cacheKey = $this->keyGenerator($request, $controller); // Use generator to create cache key
            if (Cache::has($cacheKey)) { // Return from cache if it exists in cache
                return response()->json(json_decode(Cache::get($cacheKey), true));
            }
            $response = $next($request);
            $response->getStatusCode() == 200 ? $this->addCache($response, $cacheKey, $cacheStatus) : null;
        }
        else{
            $response = $next($request); //no cache but middleware is define
        }
        return $response;
    }

    /** Add to Cache
     * Forever if cachestatus = 0
     * With expire date if cachestatus > 0
     * @param Response $response
     * @param string $cacheKey
     * @param $cacheStatus
     */
    protected function addCache($response, string $cacheKey, $cacheStatus): void
    {
        if ($cacheStatus === 0) {
            Cache::forever($cacheKey, $response->getContent()); // add to cache forever
        } else {
            Cache::put($cacheKey, $response->getContent(), $cacheStatus); //add to cache with timeout
        }
        $this->addKey($cacheKey); //add to main cache key used for tracking all keys
    }

    /** Set by client
     *  If = 1 then no cache for specific request
     * @return array|string|null
     */
    protected function noCacheRequest() : bool
    {
        return request()->header(self::NOCACHEHEADER) == 1 ? true : false;
    }

    /*** Cache Status of method.
     * > 0 : timeout defined
     * 0 :   forever
     * null : no cache
     * @param Controller $controller
     * @param string $method
     * @return int|null
     */
    public function cacheStatus($controller, string $method): ?int
    {
        $cache = null; //no cache
        if (property_exists($controller, 'cache')) {
            if (isset($controller->cache[$method])) {
                $cache = $controller->cache[$method]; //if timeout isset timeout time in seconds
            } elseif (in_array($method, $controller->cache)) { //if not timeout isset return 0
                $cache = 0;
            }
        }elseif (env('GLOBAL_CACHE')) { //if env
            $cache = 0;
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
     * getContent() gets json body payloads
     * @param Request $request
     * @param $controller
     * @return string
     */
    protected function keyGenerator(Request $request, $controller): string
    {
        return str_ireplace(["\\", '{', '}', '/', '"', ',', ':'], ["_", "_", '_', "_"], get_class($controller) . $request->getPathInfo() . $this->stringify($request->all()) . $request->getContent() . $request->getMethod() . env('APP_REAL_ENV'));
    }

    /** convert get params to json
     * @param array $array
     * @return string
     */
    protected function stringify(array $array): string
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
        Cache::put(self::INDEXKEY, implode('####', $keys));
    }

    /** Get all keys
     * @return array
     */
    public function getAllKeys()
    {
        $keysString = Cache::get(self::INDEXKEY);
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
        Cache::put(self::INDEXKEY, implode('####', $keys));
    }
}
