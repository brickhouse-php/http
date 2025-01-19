<?php

namespace Brickhouse\Http;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Brickhouse\Http\Responses\NotFound;
use Brickhouse\Support\Arrayable;
use Brickhouse\View\Engine\Exceptions\ViewNotFoundException;
use Brickhouse\View\View;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;

class Response extends HttpMessage
{
    /**
     * Gets or sets the HTTP status code of the response.
     *
     * @var integer
     */
    public int $status = HttpStatus::OK;

    /**
     * Gets or sets the body content of the response, as a stream.
     *
     * @var ReadableStream
     */
    private ReadableStream $body;

    /**
     * Callback to be invoked after the upgraded response has been written.
     *
     * @var null|(\Closure(): void)
     */
    private ?\Closure $onUpgrade = null;

    public function __construct(
        int $status = HttpStatus::OK,
        ?HttpHeaderBag $headers = null,
        null|ReadableStream|string $body = null,
        ?int $contentLength = null,
        string $protocol = "1.1",
    ) {
        parent::__construct($headers, $contentLength, $protocol);

        $this->status = $status;
        $this->body = new ReadableBuffer();

        if ($body !== null) {
            $this->setContent($body);
        }
    }

    /**
     * Creates a new `Response` instance with the given content and status code.
     *
     * @param null|ReadableStream|string    $body
     * @param int                           $status
     *
     * @return Response
     */
    public static function new(null|ReadableStream|string $body = null, $status = HttpStatus::OK): Response
    {
        $response = new Response($status);
        if ($body !== null) {
            $response->setContent($body);
        }

        return $response;
    }

    /**
     * Creates a new `Response` instance to redirect to another url.
     *
     * @param string    $url
     * @param int       $status
     *
     * @return Response
     */
    public static function redirect(string $url, int $status = HttpStatus::TEMPORARY_REDIRECT): Response
    {
        $response = new Response($status);
        $response->headers->set('Location', $url);

        return $response;
    }

    /**
     * Creates a new `Response` instance with JSON content.
     *
     * @param mixed     $content
     *
     * @return Response
     */
    public static function json(mixed $content): Response
    {
        $response = new Response();

        $content = match (true) {
            $content instanceof JsonSerializable => json_encode($content->jsonSerialize()),
            $content instanceof \Serializable => $content->serialize(),
            $content instanceof Arrayable => json_encode($content->toArray()),
            default => json_encode($content),
        };

        return $response
            ->setContentType(ContentType::JSON)
            ->setContent($content);
    }

    /**
     * Creates a new `Response` instance with HTML content.
     *
     * @param string    $content
     *
     * @return Response
     */
    public static function html(string $content): Response
    {
        $response = new Response();

        return $response
            ->setContentType(ContentType::HTML)
            ->setContent($content);
    }

    /**
     * Creates a new `Response` instance with text content.
     *
     * @param string    $content
     * @param string    $contentType
     *
     * @return Response
     */
    public static function text(string $content, string $contentType = ContentType::TXT): Response
    {
        $response = new Response();

        return $response
            ->setContentType($contentType)
            ->setContent($content);
    }

    /**
     * Creates a new `Response`-object which contains the rendered view, `$alias`, depending on the request view format.
     *
     * @param string                    $alias      Defines the alias of the view to render.
     * @param array<array-key,mixed>    $data       Defines data attributes to pass to the view.
     *
     * @return Response
     */
    public static function render(string $alias, array $data = []): Response
    {
        $extension = match (request()->format) {
            ContentType::JSON => ".json.php",
            ContentType::HTML => ".html.php",
            default => ".html.php"
        };

        $viewPath = $alias;

        // If no extension was given in the view path, attempt to guess the correct one.
        if (trim(pathinfo($alias, PATHINFO_EXTENSION)) === '') {
            $viewPath .= $extension;
        }

        try {
            $view = view($viewPath, $data);
        } catch (ViewNotFoundException) {
            // If no explicit view was found for a JSON request, we can implicitly
            // send the given data back as formatted JSON without needing a view.
            if (request()->format === ContentType::JSON) {
                // If the data array is indexed and only contains a single item,
                // unwrap it and set that back in the JSON response.
                if (array_is_list($data) && count($data) === 1) {
                    $data = reset($data);
                }

                return Response::json($data);
            }

            // Attempt to find a view which has the same alias, but a different extension.
            $view = View::findFallback($alias, $data);
            if ($view === null) {
                return new NotFound();
            }
        }

        if (str_ends_with($view->path, '.json.php')) {
            return Response::json($view->require());
        }

        return Response::html($view->render());
    }

    /**
     * Creates a new `Response`-instance from the given PSR-7 response interface.
     *
     * @param ResponseInterface $response
     *
     * @return Response
     */
    public static function psr7(ResponseInterface $response): Response
    {
        return new Response(
            status: $response->getStatusCode(),
            headers: HttpHeaderBag::parseArray($response->getHeaders()),
            body: $response->getBody(),
            contentLength: $response->getBody()->getSize(),
            protocol: $response->getProtocolVersion(),
        );
    }

    /**
     * Sets the `Content-Type` header on the response.
     *
     * @param string    $type
     *
     * @return self
     */
    public function setContentType(string $type): self
    {
        $this->headers->set(ContentType::Header, $type);
        return $this;
    }

    /**
     * Gets the content body of the response.
     *
     * @return ReadableStream
     */
    public function content(): ReadableStream
    {
        return $this->body;
    }

    /**
     * Sets the content body of the response.
     *
     * @param ReadableStream|string $body
     *
     * @return self
     */
    public function setContent(ReadableStream|string $body): self
    {
        if ($body instanceof ReadableStream) {
            $this->body = $body;
            $this->headers->remove("content-length");

            return $this;
        }

        $this->body = new ReadableBuffer($body);
        $this->headers->set("content-length", (string) \strlen($body));
        $this->contentLength = \strlen($body);

        return $this;
    }

    /**
     * Sets the callback to be invoked once the response is sent back to the client.
     *
     * @param \Closure(): void  $onUpgrade
     *
     * @return void
     */
    public function upgrade(\Closure $onUpgrade): void
    {
        $this->onUpgrade = $onUpgrade;
        $this->status = HttpStatus::SWITCHING_PROTOCOLS;
    }

    /**
     * Gets the callback to be invoked once the response is sent back to the client.
     *
     * @return null|(\Closure(): void)
     */
    public function getUpgradeHandler(): ?\Closure
    {
        return $this->onUpgrade;
    }

    /**
     * Gets whether the response is successful (i.e. status code is 200-299).
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status >= HttpStatus::OK && $this->status < HttpStatus::MULTIPLE_CHOICES;
    }
}
