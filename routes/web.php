<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Klimis\CacheMiddleware\Http\Controllers\CacherController;

Route::get('cacher/clear/all', [CacherController::class, 'clearAll']);
Route::get('cacher/clear/key', [CacherController::class, 'deleteKey']);
Route::get('cacher/get/all', [CacherController::class, 'index']);


Route::get('api/cacher/clear/all', [CacherController::class, 'clearAll']);
Route::get('api/cacher/clear/key', [CacherController::class, 'deleteKey']);
Route::get('api/cacher/get/all', [CacherController::class, 'index']);