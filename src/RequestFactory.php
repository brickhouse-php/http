<?php

namespace Brickhouse\Http;

use Laminas\Diactoros\ServerRequestFactory;

final readonly class RequestFactory
{
    /**
     * Creates a new `Request`-instance from the current superglobals (`$_GET`, `$_POST`, etc).
     *
     * @return Request
     */
    public function create(): Request
    {
        return Request::psr7(
            ServerRequestFactory::fromGlobals()
        );
    }
}
