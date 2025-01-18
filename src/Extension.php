<?php

namespace Brickhouse\Http;

use Brickhouse\Core\Application;
use Brickhouse\Http\Contracts\RouteResolver;
use Brickhouse\Http\Controllers\StaticResourceController;
use Brickhouse\Http\Commands;

class Extension extends \Brickhouse\Core\Extension
{
    /**
     * Gets the human-readable name of the extension.
     */
    public string $name = "brickhouse/http";

    public function __construct(private readonly Application $application)
    {
    }

    /**
     * Invoked before the application has started.
     */
    public function register(): void
    {
        $this->application->singleton(HttpKernel::class);
        $this->application->singleton(RouteResolver::class, DefaultRouteResolver::class);
        $this->application->singleton(Router::class);
    }

    /**
     * Invoked after the application has started.
     */
    public function boot(): void
    {
        $this->addCommands([Commands\ControllerGenerator::class, Commands\RoutesList::class]);

        Router::fallback(StaticResourceController::class);
    }
}
