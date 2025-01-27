<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Transport\ServerRequestFactory;

final readonly class RequestFactory
{
    /**
     * Creates a new `Request`-instance from the current superglobals (`$_GET`, `$_POST`, etc).
     *
     * @return Request
     */
    public function create(): Request
    {
        $request = new ServerRequestFactory()->fromGlobals();

        return new Request(
            $request->getMethod(),
            $request->uri(),
            $request->headers,
            $request->getBody(),
            $request->getBody()->getSize(),
            $request->getProtocolVersion(),
        );
    }
}
