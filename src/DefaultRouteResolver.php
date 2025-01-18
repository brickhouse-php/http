<?php

namespace Brickhouse\Http;

use Brickhouse\Http\Contracts\RouteResolver;

class DefaultRouteResolver implements RouteResolver
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @inheritDoc
     */
    public function add(string|array $methods, string $uri, \Closure $callback): self
    {
        $route = new Route($methods, $uri, $callback);

        return $this->addRoute($route);
    }

    /**
     * @inheritDoc
     */
    public function addRoute(Route $route): self
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $method, string $path): null|Route
    {
        return array_find(
            $this->routes,
            fn(Route $route) => in_array($method, array_values($route->methods)) && $route->uri === $path
        );
    }

    /**
     * @inheritDoc
     */
    public function exists(string $method, string $path): bool
    {
        return $this->resolve($method, $path) !== null;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->routes = [];
    }
}
