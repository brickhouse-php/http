<?php

namespace Brickhouse\Http\Server;

use Amp\Socket\Socket;
use Brickhouse\Log\Log;

class SocketClientFactory
{
    public function createClient(Socket $socket): ?SocketClient
    {
        $local = $socket->getLocalAddress()->toString();
        $remote = $socket->getRemoteAddress()->toString();

        /** @psalm-suppress RedundantCondition */
        Log::channel('http')->debug("Accepted {remote} on {local}", ['remote' => $remote, 'local' => $local]);

        return new SocketClient($socket);
    }
}
