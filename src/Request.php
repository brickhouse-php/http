<?php

namespace Brickhouse\Http;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use League\Uri\Uri;
use Psr\Http\Message\ServerRequestInterface;

class Request extends HttpMessage
{
    /**
     * Gets the current `Request` instance.
     *
     * @var Request
     */
    private static Request $current;

    /**
     * Contains the content of the request as a stream.
     *
     * @var ReadableStream
     */
    private ReadableStream $body;

    /**
     * Gets the matched route of the request.
     *
     * @var Route
     */
    public Route $route;

    /**
     * Gets the format requested in the request.
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

    /**
     * Defines an array of extra bindings to apply to the resolved action.
     *
     * @var array<array-key,mixed>
     */
    private array $bindings = [];

    public function __construct(
        private string $method,
        private Uri $uri,
        ?HttpHeaderBag $headers = null,
        ReadableStream|string $body = "",
        ?int $contentLength = null,
        string $protocol = "1.1"
    ) {
        parent::__construct($headers, $contentLength, $protocol);

        $this->setContent($body);

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
     * Creates a new `Request`-instance from the given PSR-7 request interface.
     *
     * @param ServerRequestInterface $request
     *
     * @return Request
     */
    public static function psr7(ServerRequestInterface $request): Request
    {
        return new Request(
            method: $request->getMethod(),
            uri: \League\Uri\Uri::fromBaseUri($request->getUri()->__toString()),
            headers: HttpHeaderBag::parseArray($request->getHeaders()),
            body: $request->getBody(),
            contentLength: $request->getBody()->getSize(),
            protocol: $request->getProtocolVersion(),
        );
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
     * Get the body content of the request.
     *
     * @return ReadableStream
     */
    public function content(): ReadableStream
    {
        return $this->body;
    }

    /**
     * Sets the body content of the request.
     *
     * @param ReadableStream|string     $body
     */
    public function setContent(ReadableStream|string $body): void
    {
        $this->body = is_string($body)
            ? new ReadableBuffer($body)
            : $body;

        $this->parameters += $this->parseJsonContent();
    }

    /**
     * Get the HTTP method of the request.
     *
     * @return non-empty-string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Sets the HTTP method of the request.
     *
     * @return void
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * Get the URI of the request.
     *
     * @return Uri
     */
    public function uri(): Uri
    {
        return $this->uri;
    }

    /**
     * Sets the URI of the request.
     *
     * @return void
     */
    public function setUri(Uri $uri): void
    {
        $this->uri = $uri;
    }

    /**
     * Get the HTTP scheme of the request.
     *
     * @return null|string
     */
    public function scheme(): ?string
    {
        return $this->uri->getScheme();
    }

    /**
     * Get the HTTP host of the request.
     *
     * @return null|string
     */
    public function host(): ?string
    {
        return $this->uri->getHost();
    }

    /**
     * Get the HTTP port of the request.
     *
     * @return null|int
     */
    public function port(): ?int
    {
        return $this->uri->getPort();
    }

    /**
     * Get the HTTP path of the request.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->uri->getPath();
    }

    /**
     * Get the HTTP query of the request.
     *
     * @return null|string
     */
    public function query(): ?string
    {
        return $this->uri->getQuery();
    }

    /**
     * Get the HTTP fragment of the request.
     *
     * @return null|string
     */
    public function fragment(): ?string
    {
        return $this->uri->getFragment();
    }

    /**
     * Gets the additional bindings of the request.
     *
     * @return array<array-key,mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * Sets the additional bindings of the request.
     *
     * @param array<array-key,mixed>    $bindings
     */
    public function setBindings(array $bindings): void
    {
        $this->bindings = $bindings;
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

        while (($chunk = $this->body->read()) !== null) {
            $bodyContent .= $chunk;
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
