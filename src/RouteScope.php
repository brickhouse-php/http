<?php

namespace Brickhouse\Http;

class RouteScope
{
    /**
     * Defines the route stack for the scope.
     *
     * @var null|RouteStack
     */
    public protected(set) null|RouteStack $stack = null;

    /**
     * Defines the prefix for the scope.
     *
     * @var string
     */
    public protected(set) string $prefix;

    /**
     * Undocumented function
     *
     * @param string    $prefix     Defines the prefix to apply to all routes in the scope.
     * @param \Closure  $callback   Callback for defining routes in the scope.
     */
    public function __construct(
        string $prefix,
        protected readonly \Closure $callback,
    ) {
        $this->prefix = trim($prefix, '/') . '/';
    }

    /**
     * Sets the stack of the scope.
     *
     * @param RouteStack    $stack
     *
     * @return self
     */
    public function stack(RouteStack $stack): self
    {
        $this->stack = $stack;

        return $this;
    }

    /**
     * Invokes the callback for the scope.
     *
     * @return void
     */
    public function __invoke()
    {
        ($this->callback)();
    }
}
