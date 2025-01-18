<?php

namespace Brickhouse\Http\Middleware;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareDelegate;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;
use Brickhouse\Log\Log;

readonly class LogRequest implements HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response
    {
        $start = hrtime(as_number: true);

        $response = $next($request);

        $elapsed = hrtime(as_number: true) - $start;

        Log::channel('http')->info(
            '{method} {route} ({elapsed} ms) - HTTP {responseCode}',
            [
                'method' => $request->method(),
                'route' => $request->path(),
                'elapsed' => number_format((float) ($elapsed / 1_000_000), 2),
                'responseCode' => $response->status,
            ]
        );

        return $response;
    }
}
