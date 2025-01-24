<?php

namespace Brickhouse\Http;

use Brickhouse\Core\Application;
use Brickhouse\Http\Contracts\RouteResolver;
use Brickhouse\Http\Responses\NotFound;
use Brickhouse\Routing\Dispatcher;
use Brickhouse\Routing\RouteCollector;
use Brickhouse\Support\Arrayable;
use Brickhouse\Support\Renderable;
use Brickhouse\Support\StringHelper;

/**
 * @phpstan-import-type FoundRoute from Dispatcher
 */
class Router
{
    use \Brickhouse\Http\Concerns\UsesHttpMiddleware;

    public function __construct(
        protected readonly Application $application,
        protected readonly RouteResolver $routeResolver,
        protected readonly RouteConfig $routeConfig
    ) {
        $this->addMiddleware([
            \Brickhouse\Http\Middleware\HandleErrors::class,
            \Brickhouse\Http\Middleware\LogRequest::class,
            \Brickhouse\Http\Middleware\CompressionMiddleware::class,
            \Brickhouse\Http\Middleware\AddServerHeader::class,
            \Brickhouse\Http\Middleware\AddFrameGuard::class,
        ]);
    }

    /** @var ?Dispatcher */
    private ?Dispatcher $dispatcher = null;

    /**
     * Gets the stack of route scopes currently used.
     *
     * @var array<int,RouteScope>
     */
    protected array $scopeStack = [];

    /**
     * Gets the fallback route to use if no path is matched.
     *
     * @var null|Route
     */
    protected null|Route $fallbackRoute = null;

    /**
     * Handle and respond to incoming HTTP requests.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $this->dispatcher ??= $this->createDispatcher();

        $method = $request->method();
        $path = rawurldecode($request->path());

        $match = $this->dispatcher->dispatch($method, $path);

        return $this->resolveRequest($request, $match);
    }

    /**
     * Adds all the routes defined in the application.
     *
     * @return void
     */
    public function addApplicationRoutes(): void
    {
        $this->addRoutesFrom(routes_path("app.php"));
    }

    /**
     * Executes the given file and adds the routes defined within in.
     *
     * @param string    $path
     *
     * @return void
     */
    public function addRoutesFrom(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        require $path;
    }

    /**
     * Create the route dispatch handler for the router.
     *
     * @return Dispatcher
     */
    private function createDispatcher(): Dispatcher
    {
        $collector = new RouteCollector();

        foreach ($this->routeResolver->all() as $route) {
            $collector->addRoute($route->methods, $route->uri, $route, $route->constraints);
        }

        return new Dispatcher($collector);
    }

    /**
     * Resolve the given request and return it's response.
     *
     * @param Request           $request
     * @param null|FoundRoute   $match
     *
     * @return Response
     */
    private function resolveRequest(Request $request, null|array $match): Response
    {
        $respond = function (Request $request) use ($match): Response {
            return match (true) {
                $match !== null => $this->found($request, $match),
                default => $this->notFound($request),
            };
        };

        $additionalMiddleware = [];

        if ($match !== null && $match[0] instanceof Route) {
            $request->route = $match[0];

            $additionalMiddleware = array_map(
                fn(string|HttpMiddleware $middleware) => when(
                    is_string($middleware),
                    resolve($middleware),
                    $middleware
                ),
                $match[0]->middleware
            );
        }

        return $this->invokeMiddleware($request, $respond, $additionalMiddleware);
    }

    /**
     * Callback for when a valid handler has been found for the given request.
     *
     * @param Request       $request
     * @param FoundRoute    $match
     *
     * @return Response
     */
    private function found(Request $request, array $match): Response
    {
        /** @var Route $route */
        [$route, $parameters] = $match;

        $request->parameters = [...$request->parameters, ...$parameters, ...$request->bindings()];
        $request->format = $this->resolveRequestFormat($request);

        $binding = $this->resolveController($route, $request);
        $result = $this->application->call($binding, $route->callback, $request->parameters);

        // Transform the result into a serializable format to respond with.
        return $this->transformResponse($result);
    }

    /**
     * Callback for when no valid handlers were been found for the given request.
     *
     * @param Request $request
     *
     * @return Response
     */
    private function notFound(Request $request): Response
    {
        if (!$this->fallbackRoute) {
            return new NotFound();
        }

        $binding = $this->resolveController($this->fallbackRoute, $request);
        $result = $this->application->call($binding, $this->fallbackRoute->callback, $request->bindings());

        // Transform the result into a serializable format to respond with.
        return $this->transformResponse($result);
    }

    /**
     * Attempts to determine which format was requested in the given request.
     *
     * @param Request $request
     *
     * @return string
     */
    private function resolveRequestFormat(Request $request): string
    {
        if (isset($request->parameters['format'])) {
            return match (strtolower($request->parameters['format'])) {
                '.csv' => ContentType::CSV,
                '.json' => ContentType::JSON,
                '.jsonld' => ContentType::JSONLD,
                '.html' => ContentType::HTML,
                '.php' => ContentType::PHP,
                '.xml' => ContentType::XML,
                default => ContentType::HTML,
            };
        }

        $acceptBag = $request->headers->accept();

        // Gets the highest priority content type in the `Accept`-header.
        if ($acceptBag !== null && ($first = $acceptBag->first()) !== null) {
            return $first->format;
        }

        // If nothing else is found, default to HTML.
        return ContentType::HTML;
    }

    /**
     * Resolve the requested controller from the given route, if any.
     *
     * @param Route     $route
     * @param Request   $request
     *
     * @return ?Controller
     */
    private function resolveController(Route $route, Request $request): ?Controller
    {
        if (!isset($route->controller)) {
            return null;
        }

        $controller = $this->application->resolve($route->controller);
        if (!$controller instanceof Controller) {
            throw new \Exception("Route returned non-Controller instance: {$route->controller}");
        }

        $controller->request = $request;

        return $controller;
    }

    /**
     * Transform the given response into valid response content.
     *
     * @param mixed     $result
     *
     * @return Response
     */
    private function transformResponse(mixed $result): Response
    {
        // `Renderable` should take precedence over `Response`, as it should be used
        // as a way to 'override' conventional response rendering.
        if ($result instanceof Renderable) {
            return Response::html($result->render());
        }

        // If the result from the action is already an instance of `Response`,
        // we don't need to transform or convert it.
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof \JsonSerializable) {
            return Response::json($result);
        }

        if ($result instanceof \Serializable) {
            return Response::json($result);
        }

        if ($result instanceof Arrayable) {
            return Response::json($result);
        }

        if ($result instanceof \Stringable) {
            return Response::text((string) $result);
        }

        if (is_bool($result) || is_string($result) || is_numeric($result)) {
            return Response::text((string) $result);
        }

        // If all else fails, attempt to convert the result to a JSON stream.
        return Response::json(json_encode($result));
    }

    /**
     * Applies all the attributes from the current route scope to the given route.
     *
     * @param Route     $route
     *
     * @return void
     */
    private function applyRouteScopeAttributes(Route &$route): void
    {
        foreach (array_reverse($this->scopeStack) as $scope) {
            // Add the scope prefix to the route.
            if ($scope->prefix !== null) {
                $route->uri = trim($scope->prefix, '/') . $route->uri;
            }

            $route->uri = StringHelper::from($route->uri)
                ->trim('/')
                ->start('/');

            // Prepend the scope middleware to the route.
            if ($scope->stack !== null && !empty($scope->stack->middleware)) {
                $route->middleware = [...$scope->stack->middleware, ...$route->middleware];
            }
        }
    }

    /**
     * Registers a new route to the router.
     *
     * @param Route     $route
     *
     * @return Route
     */
    protected static function registerRoute(Route $route): Route
    {
        $router = resolve(self::class);

        $router->applyRouteScopeAttributes($route);
        $router->routeResolver->addRoute($route);

        return $route;
    }

    /**
     * Creates a new route to the router, without adding it.
     *
     * @param array<string>|string  $methods
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     *
     * @return Route
     */
    protected function createRoute(
        array|string $methods,
        string $uri,
        callable|string $callback,
        null|string $action = null
    ): Route {
        if (is_callable($callback)) {
            $route = Route::closure($methods, $uri, $callback);
        } elseif ($action !== null) {
            $route = Route::action($methods, $uri, $callback, $action);
        } else {
            $route = Route::controller($methods, $uri, $callback);
        }

        $router = resolve(self::class);
        $router->applyRouteScopeAttributes($route);

        return $route;
    }

    /**
     * Adds a new route to the router.
     *
     * @param array<string>|string  $methods
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function route(
        array|string $methods,
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        $router = resolve(self::class);

        if (!$skipFormat) {
            $uri = rtrim($uri, '/') . ':?format';
        }

        $route = $router->createRoute($methods, $uri, $callback, $action);
        if (!$skipFormat) {
            $route->constraints([
                ...$route->constraints,
                'format' => '\.\w+',
            ]);
        }

        $router->routeResolver->addRoute($route);

        return $route;
    }

    /**
     * Adds a new route to the router, which accepts `GET` requests on the root of the application (`/`).
     *
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function root(callable|string $callback, null|string $action = null, bool $skipFormat = false): Route
    {
        return self::get("", $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts any HTTP verb requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function any(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("*", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `GET` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function get(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("GET", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `POST` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function post(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("POST", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `PUT` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function put(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("PUT", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `PATCH` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function patch(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("PATCH", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `DELETE` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function delete(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("DELETE", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Adds a new route to the router, which accepts `OPTIONS` requests.
     *
     * @param string                $uri
     * @param callable|string       $callback
     * @param null|string           $action
     * @param bool                  $skipFormat
     *
     * @return Route
     */
    public static function options(
        string $uri,
        callable|string $callback,
        null|string $action = null,
        bool $skipFormat = false,
    ): Route {
        return self::route("OPTIONS", $uri, $callback, $action, $skipFormat);
    }

    /**
     * Creates a new route stack for the router.
     *
     * @param array<int,HttpMiddleware|class-string<HttpMiddleware>>    $middleware
     *
     * @return RouteStack
     */
    public static function stack(array $middleware = []): RouteStack
    {
        return new RouteStack($middleware);
    }

    /**
     * Adds a new route scope to the router, encapsulating all routes within it under the prefix `$prefix`.
     *
     * @param string    $prefix     Prefix to scope all inner routes to.
     * @param callable  $callback   Callback for defining routes inside the scope.
     *
     * @return RouteScope
     */
    public static function scope(string $prefix, callable $callback): RouteScope
    {
        $router = resolve(self::class);
        $scope = new RouteScope($prefix, $callback);

        return tap($scope, function (RouteScope $scope) use (&$router) {
            array_push($router->scopeStack, $scope);
            $scope();
            array_pop($router->scopeStack);
        });
    }

    /**
     * Adds a new resourceful controller to the router.
     *
     * @param class-string<Controller>      $controller
     * @param array<string,string>          $names
     *
     * @return RouteScope
     */
    public static function resource(string $prefix, string $controller, array $names = []): RouteScope
    {
        return self::scope($prefix, function () use ($controller, $names) {
            if (method_exists($controller, "index")) {
                Router::get("/", $controller, "index");
            }

            if (method_exists($controller, "new")) {
                $path = $names["new"] ?? "new";
                Router::get("/{$path}", $controller, "new", skipFormat: true);
            }

            if (method_exists($controller, "create")) {
                Router::post("/", $controller, "create");
            }

            if (method_exists($controller, "show")) {
                Router::get("/:id", $controller, "show");
            }

            if (method_exists($controller, "edit")) {
                $path = $names["edit"] ?? "edit";
                Router::get("/:id/{$path}", $controller, "edit", skipFormat: true);
            }

            if (method_exists($controller, "update")) {
                Router::route(["PUT", "PATCH"], "/:id", $controller, "update");
            }

            if (method_exists($controller, "destroy")) {
                Router::delete("/:id", $controller, "destroy");
            }
        });
    }

    /**
     * Adds an array of resourceful controllers to the router.
     *
     * @param array<string,class-string<Controller>>    $resources
     * @param array<string,string>                      $names
     *
     * @return void
     */
    public static function resources(array $resources, array $names = []): void
    {
        foreach ($resources as $prefix => $resource) {
            Router::resource($prefix, $resource, $names);
        }
    }

    /**
     * Adds a new route to the router, which handles all routes which couldn't be matched.
     *
     * @param callable|string       $callback
     * @param string                $action
     *
     * @return Route
     */
    public static function fallback(callable|string $callback, string $action = "__invoke"): Route
    {
        $router = resolve(self::class);
        $route = $router->createRoute("GET", "/{path:.+}", $callback, $action);

        $router->fallbackRoute = $route;

        return $route;
    }
}
