<?php

namespace Brickhouse\Http\Server;

use Amp\ByteStream\StreamException;
use Amp\Future;
use Amp\Socket\Socket;
use Brickhouse\Channel\ChannelFactory;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;
use Brickhouse\Http\Router;
use Brickhouse\Http\Transport\HttpRequestTransport;
use Brickhouse\Http\Transport\HttpResponseTransport;
use Brickhouse\Log\Log;

use function Amp\async;

class HttpClientDriver
{
    public const int HEADER_SIZE_LIMIT = 4096;

    public const DEFAULT_BODY_SIZE_LIMIT = 131072;

    /**
     * @var Future<null>
     */
    private Future $pendingResponse;

    public function __construct(
        private readonly SocketClient $client,
        private readonly Router $router,
        private readonly ChannelFactory $channelFactory,
    ) {
        $this->pendingResponse = Future::complete();
    }

    public function handleClient(Socket $socket): void
    {
        $transport = resolve(HttpRequestTransport::class);

        try {
            $request = $transport->receive($socket);

            $this->pendingResponse = async(
                $this->handleRequest(...),
                $socket,
                $request
            );

            $this->pendingResponse->await();
        } catch (\Throwable $e) {
            Log::error("Failed to handle request: {message}", ['message' => $e->getMessage(), 'exception' => $e]);
        } finally {
            $this->pendingResponse->ignore();
        }
    }

    protected function handleRequest(Socket $socket, Request $request): void
    {
        if ($this->channelFactory->upgrade($socket, $request, $this)) {
            return;
        }

        $response = $this->router->handle($request);

        $this->send($socket, $response);
    }

    public function send(Socket $socket, Response $response, bool $close = true): void
    {
        $transport = resolve(HttpResponseTransport::class);

        try {
            $transport->send($response, $socket);
        } catch (StreamException) {
            return;
        } finally {
            if ($close) {
                $this->client->close();
            }
        }
    }

    /**
     * Determine whether the given request is a Websocket upgrade request and whether it should be upgraded.
     *
     * @param Request   $request
     *
     * @return bool
     */
    protected function shouldUpgradeToWebsocket(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        $upgradeHeader = $request->headers->get('upgrade');
        if (!$upgradeHeader || strcasecmp($upgradeHeader, 'websocket')) {
            return false;
        }

        $connectionHeader = $request->headers->get('connection');
        if (!$connectionHeader || strcasecmp($connectionHeader, 'upgrade')) {
            return false;
        }

        return true;
    }
}
