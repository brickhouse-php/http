<?php

namespace Brickhouse\Http;

final readonly class HttpMiddlewareStack
{
    /**
     * Undocumented function
     *
     * @param array<int,HttpMiddleware>     $middleware
     */
    public function __construct(
        private readonly array $middleware
    ) {}

    /**
     * Undocumented function
     *
     * @param \Closure(Request):Response $respond
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(\Closure $respond, Request $request): Response
    {
        $middlewares = [...$this->middleware];
        $next = new HttpMiddlewareDelegate($respond);

        while ($middleware = array_pop($middlewares)) {
            $next = new HttpMiddlewareDelegate(function (Request $request) use ($middleware, $next) {
                return $middleware($request, $next);
            });
        }

        return $next($request);
    }
}
