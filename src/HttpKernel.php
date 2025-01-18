<?php

namespace Brickhouse\Http;

use Brickhouse\Core\Kernel;
use Brickhouse\Http\Server\SocketServer;

class HttpKernel implements Kernel
{
    /**
     * Gets or sets the hostname to use for the HTTP server.
     *
     * @var string
     */
    protected string $hostname = "127.0.0.1";

    /**
     * Gets or sets the port to use for the HTTP server.
     *
     * @var integer
     */
    protected int $port = 8000;

    public function __construct(protected readonly Router $router)
    {
        $this->router->addApplicationRoutes();
    }

    public function invoke(array $args = [])
    {
        $endpoint = $this->resolveAddress($args);

        $server = resolve(SocketServer::class);
        $server->expose($endpoint);
        $server->serve();

        // Serve requests until SIGINT or SIGTERM is received by the process.
        $code = \Amp\trapSignal([SIGINT, SIGTERM]);

        $server->terminate();

        return $code === SIGINT ? 0 : $code;
    }

    /**
     * Attempt to resolve the address to expose the HTTP server on, from the given arguments.
     * The hostname is parsed from the `hostname` key, while the port is parsed from the `port` key in the array.
     *
     * @param array{'hostname'?: string,'port'?: int}   $args
     *
     * @return string
     */
    protected function resolveAddress(array $args): string
    {
        $hostname = $args["hostname"] ?? "127.0.0.1";
        if (!is_string($hostname)) {
            throw new \InvalidArgumentException("Invalid hostname given to HTTP kernel: '{$hostname}'");
        }

        $port = $args["port"] ?? 8000;
        if (!is_int($port)) {
            throw new \InvalidArgumentException("Invalid port given to HTTP kernel: '{$port}'");
        }

        if ($port > 65535 || $port <= 0) {
            throw new \InvalidArgumentException(
                "Invalid port given to HTTP kernel: port must be between 1-65535; given {$port}."
            );
        }

        return $hostname . ":" . $port;
    }

    /**
     * Handle and respond to incoming HTTP requests.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        return $this->router->handle($request);
    }
}
