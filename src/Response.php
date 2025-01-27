<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Responses\NotFound;
use Brickhouse\Http\Transport\ContentType;
use Brickhouse\Http\Transport\HeaderBag;
use Brickhouse\Http\Transport\Status;
use Brickhouse\Support\Arrayable;
use Brickhouse\View\Engine\Exceptions\ViewNotFoundException;
use Brickhouse\View\View;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines a standard, client-bound HTTP response.
 *
 * @phpstan-type StreamGenerator    callable():\Generator<string, void, void, void>
 */
class Response extends \Brickhouse\Http\Transport\Response
{
    /**
     * Callback to be invoked after the upgraded response has been written.
     *
     * @var null|(\Closure(): void)
     */
    private ?\Closure $onUpgrade = null;

    /**
     * Creates a new `Response` instance with JSON content.
     *
     * @param mixed     $content
     *
     * @return static
     */
    public static function json(mixed $content): static
    {
        $response = self::new();

        $content = match (true) {
            $content instanceof \JsonSerializable => json_encode($content->jsonSerialize()),
            $content instanceof \Serializable => $content->serialize(),
            $content instanceof Arrayable => json_encode($content->toArray()),
            default => json_encode($content),
        };

        // @phpstan-ignore return.type
        return $response
            ->setContentType(ContentType::JSON)
            ->setBody($content);
    }

    /**
     * Creates a new `Response` instance with streaming response content.
     *
     * @param StreamGenerator       $generator      Callable for printing chunks of the response.
     * @param int                   $status         Defines the status code of the response.
     * @param array<string,string>  $headers        Defines the headers to send along with the response.
     *
     * @return StreamingResponse
     */
    public static function stream(callable $generator, int $status = Status::OK, array $headers = []): StreamingResponse
    {
        return new StreamingResponse(
            $generator(),
            $status,
            HeaderBag::parseArray($headers)
        );
    }

    /**
     * Creates a new `Response`-object which contains the rendered view, `$alias`, depending on the request view format.
     *
     * @param string                    $alias      Defines the alias of the view to render.
     * @param array<array-key,mixed>    $data       Defines data attributes to pass to the view.
     *
     * @return static
     */
    public static function render(string $alias, array $data = []): static
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

                return self::json($data);
            }

            // Attempt to find a view which has the same alias, but a different extension.
            $view = View::findFallback($alias, $data);
            if ($view === null) {
                // @phpstan-ignore return.type
                return new NotFound();
            }
        }

        if (str_ends_with($view->path, '.json.php')) {
            return self::json($view->require());
        }

        return self::html($view->render());
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
            headers: HeaderBag::parseArray($response->getHeaders()),
            body: $response->getBody(),
            contentLength: $response->getBody()->getSize(),
            protocol: $response->getProtocolVersion(),
        );
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
}
