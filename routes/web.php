<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Klimis\CacheMiddleware\Http\Controllers\CacherController;

Route::get('/cacher', CacherController::class);
