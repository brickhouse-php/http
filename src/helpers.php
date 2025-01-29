<?php

use Brickhouse\Http\Transport\ContentType;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;

if (!function_exists("routes_path")) {
    /**
     * Gets the applications routes directory.
     *
     * @param string    $path   Optional path to append to the routes path.
     *
     * @return string
     */
    function routes_path(?string ...$path): string
    {
        return path(app()->basePath, "routes", ...$path);
    }
}

if (!function_exists("request")) {
    /**
     * Gets the current `Request`-instance of the current HTTP request.
     *
     * @return Request
     */
    function request(): Request
    {
        return Request::current();
    }
}

if (!function_exists("json")) {
    /**
     * Creates a new `Response`-object with the given JSON object as it's body content.
     *
     * @param mixed     $content        Content of the response.
     *
     * @return Response
     */
    function json(mixed $content): Response
    {
        return Response::json($content);
    }
}

if (!function_exists("text")) {
    /**
     * Creates a new `Response`-object with the given text as it's body content.
     *
     * @param string|\Stringable    $content        Content of the response.
     * @param string                $contentType    Defines the content type for the response.
     *
     * @return Response
     */
    function text(string|\Stringable $content, string $contentType = ContentType::TXT): Response
    {
        return Response::text($content, $contentType);
    }
}
