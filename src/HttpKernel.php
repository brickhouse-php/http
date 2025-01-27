<?php

namespace Brickhouse\Http;

use Brickhouse\Core\Kernel;

class HttpKernel implements Kernel
{
    public function __construct(protected readonly Router $router)
    {
        $this->router->addApplicationRoutes();
    }

    public function invoke(array $args = [])
    {
        $requestFactory = resolve(RequestFactory::class);
        $request = $requestFactory->create();

        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Sends the given request through the HTTP pipeline and produces an HTTP response.
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
