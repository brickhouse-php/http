<?php

namespace Brickhouse\Http;

class RouteStack
{
    /**
     * Gets any additional middleware which is appended onto all the routes in the stack.
     *
     * @var array<int,HttpMiddleware|class-string<HttpMiddleware>>
     */
    public protected(set) array $middleware = [];

    /**
     * Creates a new stack on the router.
     *
     * @param array<int,HttpMiddleware|class-string<HttpMiddleware>>    $middleware     Defines all the middleware in the stack.
     */
    public function __construct(
        array $middleware = [],
    ) {
        $this->middleware = $middleware;
    }
}
