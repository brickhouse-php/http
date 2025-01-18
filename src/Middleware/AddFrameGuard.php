<?php

namespace Brickhouse\Http\Middleware;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareDelegate;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;

readonly class AddFrameGuard implements HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', overwrite: false);

        return $response;
    }
}
