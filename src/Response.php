<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Responses\NotFound;
use Brickhouse\Http\Transport\ContentType;
use Brickhouse\Http\Transport\HeaderBag;
use Brickhouse\Http\Transport\Status;
use Brickhouse\Support\Arrayable;
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
     * Creates a new `Response` instance with JSON content.
     *
     * @param string                $path           Path to the file.
     * @param null|string           $contentType    Content type of the file.
     * @param array<string,string>  $headers        Headers to pass along to the response.
     *
     * @return static
     */
    public static function file(string $path, null|string $contentType = null, array $headers = []): static
    {
        $response = self::new();

        if ($contentType === null) {
            // Attempt to detect the content type of the file. If not successful, assume it's a binary file.
            $contentType = @mime_content_type($path) ?: ContentType::BIN;
        }

        // Attempt to get the absolute path to the file.
        // Note: `realpath` will return `false` if the file doesn't exist.
        $absoluteFilePath = @realpath($path) ?: base_path($path);

        $fileContent = @file_get_contents($absoluteFilePath);
        if ($fileContent === false) {
            throw new NotFound();
        }

        // @phpstan-ignore return.type
        return $response
            ->setContentType($contentType)
            ->setBody($fileContent)
            ->withHeaders($headers);
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
     * Sends the response to the output buffer.
     *
     * @return void
     */
    public function send(bool $flush = true): void
    {
        parent::send($flush);

        if (($handler = $this->getUpgradeHandler()) !== null) {
            $handler();
        }
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
