<?php

namespace Brickhouse\Http\Exceptions;

use Amp\Socket\Socket;
use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Server\SocketClient;

class HttpClientException extends \Exception
{
    public function __construct(
        public readonly Socket|SocketClient $client,
        string $message,
        int $code = HttpStatus::BAD_REQUEST,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
