<?php

namespace Brickhouse\Http;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;

class StreamedResponse extends Response
{
    public const string CHUNK_DELIMITER = "\r\n";

    /**
     * @param \Closure():\Generator     $generator
     * @param int                       $status
     * @param HttpHeaderBag|null        $headers
     * @param integer|null              $contentLength
     * @param string                    $protocol
     */
    public function __construct(
        protected readonly \Closure $generator,
        int $status = HttpStatus::OK,
        ?HttpHeaderBag $headers = null,
        ?int $contentLength = null,
        string $protocol = "1.1",
    ) {
        parent::__construct($status, $headers, $contentLength, $protocol);
    }

    /**
     * Gets the content body of the response.
     *
     * @return ReadableStream
     */
    public function content(): ReadableStream
    {
        $iteratable = function (): \Generator {
            foreach (($this->generator)() as $chunk) {
                yield $this->encodeChunk($chunk);
            }

            // Write final chunk without any content
            yield $this->encodeChunk('');
        };

        return new ReadableIterableStream($iteratable());
    }

    /**
     * Encodes the given chunk into a chunked response segment.
     *
     * [TODO] How do we more accurately find the byte-length of the given chunk?
     *
     * @param string $chunk
     *
     * @return string
     */
    protected function encodeChunk(string $chunk): string
    {
        $buffer = '';

        // Write chunk size
        $size = strlen($chunk);
        $buffer .= strtoupper(dechex($size));
        $buffer .= self::CHUNK_DELIMITER;

        // Write actual chunk content
        $buffer .= $chunk;
        $buffer .= self::CHUNK_DELIMITER;

        return $buffer;
    }
}
