<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Transport\ContentType;
use Brickhouse\Http\Transport\HeaderBag;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request extends \Brickhouse\Http\Transport\ServerRequest
{
    /**
     * Gets the current `Request` instance.
     *
     * @var Request
     */
    private static Request $current;

    /**
     * Gets the matched route of the request.
     *
     * @var Route
     */
    public Route $route;

    /**
     * Gets the formats requested in the request.
     *
     * @var string
     */
    public string $format = ContentType::HTML;

    /**
     * Gets the matched parameters in the request.
     *
     * @var array<string,mixed>
     */
    public array $parameters = [];

    public function __construct(
        string $method,
        UriInterface $uri,
        ?HeaderBag $headers = null,
        StreamInterface|string $body = "",
        ?int $contentLength = null,
        string $protocol = "1.1"
    ) {
        parent::__construct($method, $uri, $_SERVER, $headers, $body, $contentLength, $protocol);

        // Intelephense is really finicky with `$this` types.
        self::$current = ((object) $this);
    }

    /**
     * Gets the current `Request`-instance.
     *
     * @return Request
     */
    public static function current(): Request
    {
        return self::$current;
    }

    /**
     * Gets all the parameters from the request as an associative array.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * Sets the body content of the request.
     *
     * @param StreamInterface|string    $body
     */
    public function setContent(StreamInterface|string $body): void
    {
        parent::setContent($body);

        $this->parameters += $this->parseJsonContent();
    }

    /**
     * Parses the body content of the request and return it as an array.
     *
     * @return array<array-key,mixed>
     */
    protected function parseJsonContent(): array
    {
        if ($this->headers->contentType() !== ContentType::JSON) {
            return [];
        }

        $bodyContent = '';

        while (!$this->body->eof()) {
            $bodyContent .= $this->body->read(1024);
        }

        try {
            return json_decode(
                $bodyContent,
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            // TODO: should we send back an error response?
            return [];
        }
    }
}
