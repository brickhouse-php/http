<?php

namespace Brickhouse\Http\Responses;

use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Response;

final class NotFound extends Response
{
    public function __construct(string|null $body = null)
    {
        parent::__construct();

        $this->status = HttpStatus::NOT_FOUND;

        if ($body) {
            $this->setContent($body);
        }
    }
}
