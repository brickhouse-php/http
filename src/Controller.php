<?php

namespace Brickhouse\Http;

abstract class Controller
{
    /**
     * Gets the current HTTP request of the scope.
     *
     * @var Request
     */
    public Request $request;
}
