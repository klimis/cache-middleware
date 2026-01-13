<?php

/**
 * TODO:: DELETE EXPIRED KEYS FROM $indexKey
 */

/**
 * Middleware for caching Controllers responses. To use controller must:
 * 1. add methods to  cache in protected $cache = ["method1", "method2"];
 * 2. with timeout public  $cache = [
 * 'methodA' => 10
 * ];
 * 3. Setting('global.enable_global_cache'). Enable globally if cache middleware exists for method
 * 4. IMPORTANT: Cached methods with timeout are not deleted from ALL-CACHED-KEYS-KEY !!!!!
 */

namespace Klimis\CacheMiddleware\Middleware;

use App\Http\Controllers\Cms\PageController;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Request;

class CacheMiddleware
{
    const INDEXKEY = 'ALL-CACHED-KEYS-KEY'; // master main index key. we keep references here for all keys

    public $indexKey = null;

    public function __construct()
    {
        $this->indexKey = config('cache.prefix');
    }

    const NOCACHEHEADER = 'Api-Disable-Cache'; // Set this header to 0 avoid caching

    /** Check if incoming method has cache enabled. if yes returned cached results if exists. otherwise add it to cache.
     * if cache is disabled just proceed with the response
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle($request, Closure $next)
    {
        $controller = $this->getCalledController($request); // Get the calling contoller
        $method = $this->getCalledMethod($request); // Get the  method of the controller
        $cacheStatus = $this->cacheStatus($controller, $method, $request); // Timeout in seconds. if null then no cache

        if (is_numeric($cacheStatus) && ! $this->noCacheRequest() && ! env('DISABLE_CACHE')) { // If method has cache property set
            $cacheKey = $this->keyGenerator($request, $controller); // Use generator to create cache key
            if (Cache::has($cacheKey)) { // Return from cache if it exists in cache

                $cachedContent = json_decode(Cache::get($cacheKey), true);
                $cachedContent['metadata']['cache_key'] = $cacheKey; // add key here so we can endpoints that are requested through cms modules

                // Log::debug($cacheKey);
                return response()
                    ->json($cachedContent)
                    ->header('X-Is-From-Coin-Cache', true)
                    ->header('X-CC', $cacheKey);
            }
            $response = $next($request);
            $response->getStatusCode() == 200 ? $this->addCache($response, $cacheKey, $cacheStatus) : null;
        } else {
            $response = $next($request); // no cache but middleware is define
        }

        return $response;
    }

    /** Add to Cache
     * Forever if cachestatus = 0
     * With expire date if cachestatus > 0
     *
     * @param  Response  $response
     */
    protected function addCache($response, string $cacheKey, $cacheStatus): void
    {
        if ($cacheStatus === 0) {
            Cache::forever($cacheKey, $response->getContent()); // add to cache forever
        } else {
            Cache::put($cacheKey, $response->getContent(), $cacheStatus); // add to cache with timeout
        }
        $this->addKey($cacheKey); // add to main cache key used for tracking all keys
    }

    /** Set by client
     *  If = 1 then no cache for specific request
     *
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
        $cache = null; // no cache
        if (property_exists($controller, 'cache')) {
            $isException = $this->isException($controller, $method, $request);
            if (! $isException) {
                if (isset($controller->cache[$method])) {
                    $cache = $controller->cache[$method]; // if timeout isset timeout time in seconds
                } elseif (in_array($method, $controller->cache)) { // if not timeout isset return 0
                    $cache = 0;
                }
            }
        } elseif (env('GLOBAL_CACHE')) { // if env
            $cache = 0;
        }

        return $cache;
    }

    /** Fucntion to check if page_type belongs to exceptions
     * This is to allow specific cms/page requests to NOT be cached. eg page_type data-hub-explorer that fetches random results
     */
    protected function isException($controller, string $method, Request $request): bool
    {
        // FOR PAGE TYPE ONLY AT THE MOMENT. FOR CMS/PAGE REQUESTS. EXCLUDE PAGES
        $exception = false;
        $content = json_decode($request->getContent(), true); // get payload
        $pageType = null;
        if (isset($content['page_type']) && isset($controller->cacheExceptions[$method]['page_type'])) {
            $pageType = $content['page_type'];
            if (in_array($pageType, $controller->cacheExceptions[$method]['page_type'])) {
                $exception = true;
            } else {
                $exception = false;
            }
        }

        return $exception;
    }

    /** Get Controller as class
     */
    protected function getCalledController(Request $request): mixed
    {
        $data = explode('@', $request->route()->action['uses']);

        return new $data[0];
    }

    protected function getCalledMethod(Request $request): string
    {
        $data = explode('@', $request->route()->action['uses']);

        return $data[1];
    }

    /**
     * Take controller namespace , path, and method
     * getContent() gets json body payloads
     */
    protected function keyGenerator(Request $request, $controller): string
    {
        $subKeys = $this->subCases($request, $controller);

        $requestAll = $request->all();
        if (is_array($requestAll)) {
            if (count($requestAll) === 0) {
                $requestAll = $request->getContent();
                if ($requestAll) {
                    $requestAll = json_decode($requestAll, true);
                } else {
                    $requestAll = [];
                }
            }
        }
        $requestContent = $request->getContent();
        // Env independant cache
        $params = $request->path().$this->stringify($requestAll).str_replace(["\n", "\r", ' '], '', ($requestContent)).'_'.$request->getMethod();
        $key = str_ireplace(['\\', '{', '}', '//', '"', ',', ':', '[', ']', ' '], ['_'], $params);
        $keyFinal = sprintf('%s|%s|%s|%s|%s', md5($key), $subKeys['source'], $subKeys['type'], $subKeys['code'], self::getPathLastPart($request));

        return $keyFinal;
    }

    public static function getPathLastPart($request): string
    {
        $path = $request->path();
        $pathParts = str_replace('api/v1/en/', '', $path);

        return $pathParts;

    }

    /** Add extra params to cache key to identify for purging when required
     */
    protected function subCases(Request $request, Controller $controller): array
    {
        $code = null;
        $type = null;
        $source = null;
        $map = null;

        $path = $request->path();

        $content = $request->getContent();
        $content = json_decode($content);

        // $map = $request->json()->get('map');
        if (isset($content->map)) {
            $map = json_decode(json_encode($content->map), true);
        }

        $unitcode = $request->get('unit_code');
        $indexcode = $request->get('indexcode');
        $component = $request->get('component');
        $category = $request->get('category') ?? $request->get('cat_id');
        $statsType = $request->get('statistics_type');
        $cacheType = $request->get('cch_type');

        if (! $indexcode) {
            $indexcode = $request->get('index_code');
        }
        if (! $indexcode) {
            $indexcode = $request->get('index_code_similar');
        }
        if (! $component) {
            $component = $request->get('componentid');
        }
        if (! $component) {
            $component = $request->get('components');
        }
        if (! $component) {
            $component = $request->get('comp_id');
        }

        if (isset($indexcode)) {
            $code = $indexcode;
            $type = 'indice';
            $source = 'query';
        } elseif (isset($map['code'])) { // this for client side requests
            $code = $map['code'];
            $type = $map['type'];
            if ($controller instanceof PageController) {
                $source = 'cms-page';
            } else {
                $source = 'query';
            }
        } elseif (isset($map['index_code'])) { // this for client side requests
            $code = $map['index_code'];
            $type = 'indice';
            $source = 'query';
        } elseif (isset($map['unit_code'])) { // this for client side requests
            $code = $map['unit_code'];
            $type = 'unit';
            $source = 'query';
        } elseif (isset($map['component'])) { // this for client side requests
            $code = $map['component'];
            $type = 'component';
            $source = 'query';
        } elseif ($component) { // this for client side requests
            $code = $component;
            $type = 'component';
            $source = 'query';
        } elseif ($unitcode && $category) { // this for client side requests
            $code = $unitcode;
            $type = 'unit,category';
            $source = 'query';
        } elseif ($unitcode) { // this for client side requests
            $code = $unitcode;
            $type = 'unit';
            $source = 'query';
        } elseif ($statsType) { // this for client side requests
            $code = $statsType;
            $type = 'statistics';
            $source = 'query';
        } elseif ($category) { // this for client side requests
            $code = $category;
            $type = 'category';
            $source = 'query';
        }
        if ($cacheType === 'scatter') { // this for client side requests
            $code = $indexcode;
            $type = 'index';
            $source = 'scatter';
        }

        return ['code' => $code, 'type' => $type, 'source' => $source];
    }

    /** convert get params to json
     */
    protected function stringify(array $array): string
    {
        return collect($array)->toJson();
    }

    /** Create key if not exists in reference cache key
     */
    public function addKey(string $key): void
    {
        $keys = $this->getAllKeys();
        if (is_array($keys) && ! in_array($key, $keys)) {
            array_push($keys, $key);
        }
        Cache::put($this->indexKey, implode('####', $keys));
    }

    /** Get all keys
     */
    public function getAllKeys(): array
    {

        $keysString = Cache::get($this->indexKey);
        if ($keysString && (strpos($keysString, '####') !== false)) {
            $allKeys = explode('####', $keysString);
        } else {
            $allKeys[] = $keysString;
        }

        return array_filter($allKeys);
    }

    /** Remove key from index  key
     */
    public function removeKey(string $keytodel): void
    {
        $keys = $this->getAllKeys();
        if (($key = array_search($keytodel, $keys)) !== false) {
            unset($keys[$key]);
        }
        Cache::put($this->indexKey, implode('####', $keys));
    }
}
