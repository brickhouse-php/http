<?php

namespace Brickhouse\Http\Concerns;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareStack;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;

trait UsesHttpMiddleware
{
    /**
     * Gets all the global middleware.
     *
     * @var array<int,HttpMiddleware>
     */
    protected array $middleware = [];

    /**
     * Adds the given middleware class to the stack.
     *
     * @param class-string<HttpMiddleware>|array<int,class-string<HttpMiddleware>>  $middleware
     *
     * @return void
     */
    protected function addMiddleware(string|array $middleware): void
    {
        $middleware = array_wrap($middleware);
        $middleware = array_map('resolve', $middleware);

        $this->middleware = [...$this->middleware, ...$middleware];
    }

    /**
     * Invoke the global middleware with the given request.
     *
     * @param Request $request
     * @param \Closure(Request):Response $respond
     * @param array<int,HttpMiddleware> $additional
     *
     * @return Response
     */
    protected function invokeMiddleware(
        Request $request,
        \Closure $respond,
        array $additional = []
    ): Response {
        $middleware = array_merge($this->middleware, $additional);
        $stack = new HttpMiddlewareStack($middleware);

        return $stack($respond, $request);
    }
}
