<?php

namespace Brickhouse\Http;

final readonly class HttpMiddlewareStack
{
    /**
     * Undocumented function
     *
     * @param array<int,class-string<HttpMiddleware>> $middleware
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
        $middleware = [...$this->middleware];
        $next = new HttpMiddlewareDelegate($respond);

        while ($middlewareClass = array_pop($middleware)) {
            $next = new HttpMiddlewareDelegate(function (Request $request) use ($middlewareClass, $next) {
                $middleware = resolve($middlewareClass);

                return $middleware($request, $next);
            });
        }

        return $next($request);
    }
}
