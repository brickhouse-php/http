<?php

namespace Brickhouse\Http\Middleware;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareDelegate;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;
use Brickhouse\Log\Log;

readonly class HandleErrors implements HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Log::error(
                "Unhandled exception: {message}",
                [
                    'message' => $e->getMessage(),
                    'exception' => $e
                ]
            );

            echo ($e->getMessage() . PHP_EOL);
            echo ($e->getTraceAsString() . PHP_EOL);

            if ($e->getPrevious()) {
                echo ($e->getPrevious()->getMessage() . PHP_EOL);
                echo ($e->getPrevious()->getTraceAsString() . PHP_EOL);
            }

            throw $e;
        }

        return $response;
    }
}
