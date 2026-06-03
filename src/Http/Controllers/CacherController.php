<?php

declare(strict_types=1);

namespace Klimis\CacheMiddleware\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Klimis\CacheMiddleware\Middleware\CacheMiddleware;

class CacherController extends BaseController
{
    const CACHE_KEY_NAME = 'cachekey';

    public function index(Request $request)
    {
        $cache = new CacheMiddleware;
        return response()->json($cache->getAllKeys());

    }
    public function clearAll(): JsonResponse
    {
        $cache = new CacheMiddleware;
        $keys = $cache->getAllKeys();
        // loop keys and delete one by one
        foreach ($keys as $key) {
            Cache::forget($key);
            $cache->removeKey($key);
        }

        return response()->json(['message' => 'Full Cache Cleared']);
    }
    public function deleteKey(Request $request)
    {

        $cache = new CacheMiddleware;
        $key = $request->header(self::CACHE_KEY_NAME);

        if ($key) {
            if (Cache::forget($key)) {
                $cache->removeKey($key);
                return response()->json(['message' => 'Cache key Cleared:'.$key]);
            }
        }
        return response()->json(['message' => 'key not found'], 404);

    }
}
