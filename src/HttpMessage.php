<?php

namespace Brickhouse\Http;

abstract class HttpMessage
{
    /**
     * Gets the header bag for the message.
     *
     * @var HttpHeaderBag
     */
    public readonly HttpHeaderBag $headers;

    public function __construct(
        ?HttpHeaderBag $headers = null,
        protected ?int $contentLength = null,
        protected string $protocol = "1.1",
    ) {
        $this->headers = $headers ?? HttpHeaderBag::empty();
    }

    /**
     * Get the length of the request in bytes.
     *
     * @return null|int
     */
    public function length(): ?int
    {
        return $this->contentLength;
    }

    /**
     * Set the length of the request in bytes.
     *
     * @param int $length
     *
     * @return void
     */
    public function setLength(int $length): void
    {
        $this->contentLength = $length;
    }

    /**
     * Get the HTTP protocol of the request.
     *
     * @return string
     */
    public function protocol(): string
    {
        return $this->protocol;
    }
}
