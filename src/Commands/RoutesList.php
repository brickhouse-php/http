<?php

namespace Brickhouse\Http\Commands;

use Brickhouse\Console\Command;
use Brickhouse\Http\Contracts\RouteResolver;
use Brickhouse\Http\Router;

class RoutesList extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    public string $name = 'list:routes';

    /**
     * The description of the console command.
     *
     * @var string
     */
    public string $description = 'List all the routes in the application.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $router = resolve(Router::class);
        $router->addApplicationRoutes();

        $routeResolver = resolve(RouteResolver::class);

        foreach ($routeResolver->all() as $route) {
            foreach ($route->methods as $method) {
                $methodAccent = match ($method) {
                    'GET' => 'text-green-300',
                    'POST' => 'text-amber-300',
                    'PATCH' => 'text-lime-300',
                    'PUT' => 'text-blue-300',
                    'DELETE' => 'text-red-300',
                    'OPTIONS' => 'text-purple-300',
                    default => 'text-black'
                };

                $handler = match (true) {
                    $route->action && $route->controller => "[{$route->controller}::{$route->action}]",
                    $route->controller => "[{$route->controller}]",
                    default => "Closure",
                };

                $this->writeHtml(<<<HTML
                    <span class='max-w-full flex mr-2'>
                        <span class='w-8 {$methodAccent}'>{$method}</span>
                        <span class='flex-1'>{$route->uri}</span>
                        <span class='text-right text-black'>{$handler}</span>
                    </span>
                HTML);
            }
        }

        return 0;
    }
}
