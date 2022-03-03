<?php
/**
 * TODO:: DELETE EXPIRED KEYS FROM $indexKey
 */

/**
 * Middleware for caching Controllers responses. To use controller must:
 * 1. extend from ApiController
 * 2. add methods to  cache in protected $cache = ["method1","method2"];
 * 3. with timeout public  $cache = [
 * 'methodA' => 10
 * ];
 *  4. setting('global.enable_global_cache'). Enable globally if cache middleware exists for method
 * 5. IMPORTANT: Cached methods with timeout are not deleted from ALL-CACHED-KEYS-KEY !!!!!
 */

namespace Klimis\CacheMiddleware\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Request;

class CacheMiddleware
{
    const INDEXKEY = 'ALL-CACHED-KEYS-KEY'; // master main index key. we keep references here for all keys
    const NOCACHEHEADER = 'Api-Disable-Cache'; //Set this header to 0 avoid caching

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
        $cacheStatus = $this->cacheStatus($controller, $method, $request); // Timeout in seconds. if null then no cache

        if (is_numeric($cacheStatus) && !$this->noCacheRequest() && !env('DISABLE_CACHE')) { // If method has cache property set
            $cacheKey = $this->keyGenerator($request, $controller); // Use generator to create cache key
            if (Cache::has($cacheKey)) { // Return from cache if it exists in cache
                return response()
                    ->json(json_decode(Cache::get($cacheKey), true))
                    ->header('X-Is-From-Coin-Cache', true)
                    ->header('X-CC', $cacheKey);
            }
            $response = $next($request);
            $response->getStatusCode() == 200 ? $this->addCache($response, $cacheKey, $cacheStatus) : null;
        } else {
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
    protected function noCacheRequest(): bool
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
    public function cacheStatus($controller, string $method, Request $request): ?int
    {
        $cache = null; //no cache
        if (property_exists($controller, 'cache')) {
            $isException = $this->isException($controller, $method, $request);
            if (!$isException) {
                if (isset($controller->cache[$method])) {
                    $cache = $controller->cache[$method]; //if timeout isset timeout time in seconds
                } elseif (in_array($method, $controller->cache)) { //if not timeout isset return 0
                    $cache = 0;
                }
            }
        } elseif (env('GLOBAL_CACHE')) { //if env
            $cache = 0;
        }
        return $cache;
    }

    /** Fucntion to check if page_type belongs to exceptions
     * This is to allow specific cms/page requests to NOT be cached. eg page_type data-hub-explorer that fetches random results
     * @param $controller
     * @param string $method
     * @param Request $request
     * @return bool
     */
    protected function isException($controller, string $method, Request $request): bool
    {
        //FOR PAGE TYPE ONLY AT THE MOMENT. FOR CMS/PAGE REQUESTS. EXCLUDE PAGES
        $exception = false;
        $content = json_decode($request->getContent(), true); //get payload
        $pageType = null;
        if (isset($content['page_type']) && isset($controller->cacheExceptions[$method]['page_type'])) {
            $pageType = $content['page_type'];
            if (in_array($pageType, $controller->cacheExceptions[$method]['page_type'])) {
                $exception = true;
            } else {
                $exception = false;
            }
        };
        return $exception;
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
        $params = $this->stringify($request->all()) . $request->getContent() . "_" . $request->getMethod() . "_" . env('APP_REAL_ENV');
        $key = str_ireplace(["\\", '{', '}', '//', '"', ',', ':', '[', ']',' '], ["_"], $request->path() . $params);
        $key = trim(preg_replace('~[\r\n]+~', '_', $key)); //replace new lines
        return md5($key);
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
