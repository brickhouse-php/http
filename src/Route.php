<?php

namespace Brickhouse\Http;

class Route
{
    /**
     * Gets the applicable HTTP methods for the route.
     *
     * @var array<int,string>
     */
    public array $methods;

    /**
     * Gets the url relative to the host.
     *
     * @var string
     */
    public string $uri;

    /**
     * Gets the callback to invoke.
     *
     * @var \Closure
     */
    public \Closure $callback;

    /**
     * Gets the parent controller containing the route, if any.
     *
     * @var ?string
     */
    public ?string $controller = null;

    /**
     * Gets the action on the controller to execute, if any.
     *
     * @var ?string
     */
    public ?string $action = null;

    /**
     * Gets any additional middleware which is appended onto the route.
     *
     * @var array<int,HttpMiddleware|class-string<HttpMiddleware>>
     */
    public array $middleware = [];

    /**
     * Gets any additional regex constraints for use in route parameters.
     *
     * @var array<string,string>
     */
    public array $constraints = [];

    /**
     * @param string|list<string>   $methods
     * @param string                $uri
     * @param \Closure              $callback
     */
    public function __construct(string|array $methods, string $uri, \Closure $callback)
    {
        $this->methods = is_array($methods) ? $methods : [$methods];

        if (!str_starts_with($uri, "/")) {
            $uri = "/" . $uri;
        }

        $this->uri = $uri;
        $this->callback = $callback;
    }

    /**
     * Create a new `Route` instance with an anonymous closure callback.
     *
     * @param string|list<string>   $methods
     * @param string                $uri
     * @param \Closure              $callback
     *
     * @return Route
     */
    public static function closure(string|array $methods, string $uri, \Closure $callback): Route
    {
        return new Route($methods, $uri, $callback);
    }

    /**
     * Create a new `Route` instance with an invokable controller.
     *
     * @param string|list<string>       $methods
     * @param string                    $uri
     * @param class-string<Controller>  $controller
     *
     * @return Route
     */
    public static function controller(string|array $methods, string $uri, string $controller): Route
    {
        return static::action($methods, $uri, $controller, "__invoke");
    }

    /**
     * Create a new `Route` instance with a controller action.
     *
     * @param string|list<string>       $methods
     * @param string                    $uri
     * @param class-string<Controller>  $controller
     * @param non-empty-string          $action
     *
     * @return Route
     */
    public static function action(string|array $methods, string $uri, string $controller, string $action): Route
    {
        $instance = resolve($controller);
        $callback = $instance->{$action}(...);

        $route = new Route($methods, $uri, $callback);
        $route->controller = $controller;
        $route->action = $action;

        return $route;
    }

    /**
     * Append a single middleware or a stack of middleware onto the given route.
     *
     * @param HttpMiddleware|class-string<HttpMiddleware>|array<int,HttpMiddleware|class-string<HttpMiddleware>> $middleware
     *
     * @return self
     */
    public function withMiddleware(array|string|HttpMiddleware $middleware): self
    {
        $this->middleware = array_wrap($middleware);
        return $this;
    }

    /**
     * Adds the given constraints to the route, for use in route parameters.
     *
     * @param array<string,string>  $constraints
     *
     * @return self
     */
    public function constraints(array $constraints): self
    {
        $this->constraints = $constraints;
        return $this;
    }
}
