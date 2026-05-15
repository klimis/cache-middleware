<?php

declare(strict_types=1);

namespace Klimis\CacheMiddleware\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

class CacherController extends BaseController
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
