<?php

namespace Brickhouse\Http\Contracts;

use Brickhouse\Http\Route;

interface RouteResolver
{
    /**
     * Get all the routes in the cache.
     *
     * @return list<Route>
     */
    public function all(): array;

    /**
     * Add a route to the cache.
     *
     * @param string|list<string>   $methods    Defines which methods the route is accesible by.
     * @param non-empty-string      $path       Defines the path of the route.
     * @param \Closure              $callback   Defines the callback to invoke.
     *
     * @return self
     */
    public function add(string|array $methods, string $path, \Closure $callback): self;

    /**
     * Add a route to the cache.
     *
     * @param Route         $route
     *
     * @return self
     */
    public function addRoute(Route $route): self;

    /**
     * Resolves the route which matches the given HTTP verbs and path. If none is found, returns `null`.
     *
     * @param non-empty-string      $method     Defines which HTTP method the route is accesible by.
     * @param non-empty-string      $path       Defines the path of the route.
     *
     * @return null|Route
     */
    public function resolve(string $method, string $path): null|Route;

    /**
     * Get whether any route in the cache matches the given parameters.
     *
     * @param non-empty-string      $method     Defines which HTTP method the route is accesible by.
     * @param non-empty-string      $path       Defines the path of the route.
     *
     * @return bool
     */
    public function exists(string $method, string $path): bool;

    /**
     * Clear the cache of all routes.
     */
    public function invalidate(): void;
}
