<?php

namespace Brickhouse\Http\Middleware;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareDelegate;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;

readonly class AddServerHeader implements HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response
    {
        $response = $next($request);

        $response->headers->set('Server', 'Brickhouse');

        return $response;
    }
}
