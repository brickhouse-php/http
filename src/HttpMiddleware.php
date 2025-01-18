<?php

namespace Brickhouse\Http;

interface HttpMiddleware
{
    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response;
}
