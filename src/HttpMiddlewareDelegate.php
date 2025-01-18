<?php

namespace Brickhouse\Http;

final readonly class HttpMiddlewareDelegate
{
    public function __construct(
        private \Closure $next
    ) {}

    public function __invoke(Request $request): Response
    {
        return ($this->next)($request);
    }
}
