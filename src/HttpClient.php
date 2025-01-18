<?php

namespace Brickhouse\Http;

use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Brickhouse\Http\Transport\HttpRequestTransport;
use Brickhouse\Http\Transport\HttpResponseTransport;
use League\Uri\Uri;

use function Amp\Socket\{connect, connectTls};

class HttpClient
{
    protected Request $pendingRequest;

    /**
     * Gets the query parameters for the current request.
     *
     * @var array<string,mixed>
     */
    protected array $queryParameters = [];

    public function __construct(
        public float $timeout = 30.0,
        public bool $nodelay = false
    ) {
        $this->pendingRequest = new Request("GET", Uri::new());
    }

    /**
     * Sets which content type the client accepts in the response.
     *
     * @param string    $contentType
     *
     * @return self
     */
    public function accepts(string $contentType): self
    {
        return $this->header("accept", $contentType, overwrite: true);
    }

    /**
     * Sets content type the client accepts in the response to `application/json`.
     *
     * @return self
     */
    public function acceptsJson(): self
    {
        return $this->accepts(ContentType::JSON);
    }

    /**
     * Sets which content type the client sends in the request body.
     *
     * @param string    $contentType
     *
     * @return self
     */
    public function contentType(string $contentType): self
    {
        return $this->header("Content-Type", $contentType, overwrite: true);
    }

    /**
     * Adds the given header to the request.
     *
     * @param string    $name
     * @param string    $value
     * @param bool      $overwrite  Whether to overwrite the existing header, if any.
     *
     * @return self
     */
    public function header(
        string $name,
        string $value,
        bool $overwrite = true
    ): self {
        $this->pendingRequest->headers->set($name, $value, $overwrite);
        return $this;
    }

    /**
     * Adds the given headers to the request.
     *
     * @param array<string,string>  $headers
     * @param bool                  $overwrite  Whether to overwrite the existing header, if any.
     *
     * @return self
     */
    public function headers(array $headers, bool $overwrite = true): self
    {
        foreach ($headers as $key => $value) {
            $this->header($key, $value, $overwrite);
        }

        return $this;
    }

    /**
     * Sets the amount of seconds before the request times out.
     *
     * @param float     $timeout
     *
     * @return self
     */
    public function timeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Adds the given query parameter(s) to the request.
     *
     * @param string|array<string,mixed>    $keyOrValues
     * @param mixed                         $value
     *
     * @return self
     */
    public function query(string|array $keyOrValues, mixed $value = null): self
    {
        if (is_string($keyOrValues)) {
            $this->queryParameters[$keyOrValues] = $value;
        } else {
            $this->queryParameters += $keyOrValues;
        }

        $queryString = join(
            "&",
            array_map(
                function (string $key, null|string $value) {
                    $key = htmlentities($key);

                    if ($value !== null) {
                        return $key . "=" . htmlentities((string) $value);
                    }

                    return $key;
                },
                array_keys($this->queryParameters),
                array_values($this->queryParameters)
            )
        );

        $this->pendingRequest->setUri(
            // @phpstan-ignore argument.type
            $this->pendingRequest->uri()->withQuery($queryString)
        );

        return $this;
    }

    /**
     * Sets the fragment of the query URI.
     *
     * @param string        $fragment
     *
     * @return self
     */
    public function fragment(string $fragment): self
    {
        $this->pendingRequest->setUri(
            // @phpstan-ignore argument.type
            $this->pendingRequest->uri()->withFragment($fragment)
        );

        return $this;
    }

    /**
     * Sets the body content of the request.
     *
     * @param \Stringable|string    $body
     * @param string                $contentType
     *
     * @return self
     */
    public function body(
        \Stringable|string $body,
        string $contentType = ContentType::BIN
    ): self {
        $this->pendingRequest->headers->set("Content-Type", $contentType);
        $this->pendingRequest->setContent($body);

        return $this;
    }

    /**
     * Sends an HTTP GET request using to the given URI and returns the response.
     *
     * @param string                $uri
     * @param array<string,mixed>   $queryParameters
     *
     * @return Response
     */
    public function get(string $uri, array $queryParameters = []): Response
    {
        $this->pendingRequest->setMethod("GET");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        $this->query($queryParameters);

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP POST request using to the given URI and returns the response.
     *
     * @param string                        $uri
     * @param null|array<string,mixed>      $data
     *
     * @return Response
     */
    public function post(null|string $uri, null|array $data = null): Response
    {
        $this->pendingRequest->setMethod("POST");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        if ($data !== null) {
            $this->body(json_encode($data), ContentType::JSON);
        }

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP PATCH request using to the given URI and returns the response.
     *
     * @param string    $uri
     *
     * @return Response
     */
    public function patch(null|string $uri): Response
    {
        $this->pendingRequest->setMethod("PATCH");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP PUT request using to the given URI and returns the response.
     *
     * @param string    $uri
     *
     * @return Response
     */
    public function put(null|string $uri): Response
    {
        $this->pendingRequest->setMethod("PUT");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP DELETE request using to the given URI and returns the response.
     *
     * @param string    $uri
     *
     * @return Response
     */
    public function delete(null|string $uri): Response
    {
        $this->pendingRequest->setMethod("DELETE");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP HEAD request using to the given URI and returns the response.
     *
     * @param string    $uri
     *
     * @return Response
     */
    public function head(null|string $uri): Response
    {
        $this->pendingRequest->setMethod("HEAD");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP OPTIONS request using to the given URI and returns the response.
     *
     * @param string    $uri
     *
     * @return Response
     */
    public function options(null|string $uri): Response
    {
        $this->pendingRequest->setMethod("OPTIONS");
        $this->pendingRequest->setUri(Uri::fromBaseUri($uri));

        return $this->send($this->pendingRequest);
    }

    /**
     * Sends an HTTP request using to the given URI and returns the response.
     *
     * @param string    $method
     * @param string    $uri
     *
     * @return Response
     */
    public function call(string $method, string $uri): Response
    {
        $request = new Request($method, Uri::fromBaseUri($uri));

        return $this->send($request);
    }

    /**
     * Sends the given HTTP request and returns the response.
     *
     * @param Request   $request
     *
     * @return Response
     */
    public function send(Request $request): Response
    {
        // If a `Host` header isn't attached, set it to the destination hostname.
        if (!$request->headers->has("host")) {
            $request->headers->set("host", $request->uri()->getHost());
        }

        $connectContext = $this->createConnectContext($request);

        $host = $request->uri()->getHost();
        $port = $request->uri()->getPort();

        if ($port === null) {
            $port = $connectContext->getTlsContext() !== null ? 443 : 80;
        }

        $uri = $host . ":" . $port;

        $socket =
            $connectContext->getTlsContext() !== null
                ? connectTls($uri, $connectContext)
                : connect($uri, $connectContext);

        $transmitTransport = resolve(HttpRequestTransport::class);
        $receiveTransport = resolve(HttpResponseTransport::class);

        try {
            $transmitTransport->send($request, $socket);

            return $receiveTransport->receive($socket);
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $socket->end();
        }
    }

    /**
     * Create a `ConnectContext` from the given request instance.
     *
     * @param Request $request
     *
     * @return ConnectContext
     */
    protected function createConnectContext(Request $request): ConnectContext
    {
        $connectContext = new ConnectContext();

        if ($this->nodelay) {
            $connectContext->withTcpNoDelay();
        } else {
            $connectContext->withoutTcpNoDelay();
        }

        if ($request->scheme() === "https") {
            $host = $request->host() ?? "";
            $connectContext->withTlsContext(new ClientTlsContext($host));
        }

        return $connectContext;
    }
}
