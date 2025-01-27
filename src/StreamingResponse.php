<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Transport\HeaderBag;
use Brickhouse\Http\Transport\Status;

/**
 * Defines a streaming, client-bound HTTP response.
 */
class StreamingResponse extends Response
{
    public function __construct(
        protected readonly \Generator $generator,
        int $status = Status::OK,
        ?HeaderBag $headers = null,
        string $protocol = "1.1"
    ) {
        parent::__construct($status, $headers, protocol: $protocol);

        $this->headers->set('Transfer-Encoding', 'chunked');
        $this->headers->remove('Content-Length');
    }

    /**
     * Sends the content of the response to the output buffer.
     *
     * @return void
     */
    protected function sendContent(): void
    {
        foreach ($this->generator as $value) {
            $length = dechex(strlen($value));
            $chunk = $length . self::CHUNK_DELIMITER;
            $chunk .= $value . self::CHUNK_DELIMITER;

            echo $chunk;
            ob_flush();
            flush();
        }

        echo "0" . self::CHUNK_DELIMITER . self::CHUNK_DELIMITER;
        ob_flush();
        flush();
    }
}
