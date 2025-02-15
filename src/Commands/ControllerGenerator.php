<?php

namespace Brickhouse\Http\Commands;

use Brickhouse\Console\Attributes\Argument;
use Brickhouse\Console\Attributes\Option;
use Brickhouse\Console\GeneratorCommand;
use Brickhouse\Console\InputOption;
use Brickhouse\Core\AppConfig;
use Brickhouse\Support\StringHelper;

class ControllerGenerator extends GeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    public string $name = 'generate:controller';

    /**
     * The description of the console command.
     *
     * @var string
     */
    public string $description = 'Scaffolds a new controller.';

    /**
     * Defines the name of the generated controller.
     *
     * @var string
     */
    #[Argument("name", "Specifies the name of the controller", InputOption::REQUIRED)]
    public string $controllerName = '';

    /**
     * Defines whether to add API controllers.
     *
     * @var boolean
     */
    #[Option("api", null, "Defines whether to add API controllers.", InputOption::NEGATABLE)]
    public bool $api = false;

    /**
     * DDefines whether to create a corresponding model.
     *
     * @var boolean
     */
    #[Option("model", null, "Defines whether to create a corresponding model.", InputOption::NEGATABLE)]
    public bool $addModel = true;

    /**
     * Defines whether to create views for the controller routes.
     *
     * @var boolean
     */
    #[Option("views", null, "Defines whether to create views for the controller routes.", InputOption::NEGATABLE)]
    public bool $addViews = true;

    /**
     * Determines whether to create API controllers or normal controllers.
     *
     * @return boolean
     */
    protected function isApiController(): bool
    {
        return $this->api || resolve(AppConfig::class)->api_only;
    }

    /**
     * @inheritDoc
     */
    protected function sourceRoot(): string
    {
        return __DIR__ . '/../Stubs/';
    }

    /**
     * @inheritDoc
     */
    public function handle(): int
    {
        $this->controllerName = StringHelper::from($this->controllerName)
            ->end('Controller')
            ->__toString();

        $stub = $this->isApiController()
            ? 'Controller.api.stub.php'
            : 'Controller.stub.php';

        $this->copy(
            $stub,
            path('src', 'Controllers', $this->controllerName . '.php'),
            [
                'controllerNamespace' => 'App\\Controllers',
                'controllerClass' => $this->controllerName,
                'modelClass' => $this->getBaseName(),
            ]
        );

        $this->createModel();
        $this->createViews();

        return 0;
    }

    /**
     * Creates the corresponding model for the controller, if requested.
     *
     * @return void
     */
    protected function createModel(): void
    {
        if (!$this->addModel) {
            return;
        }

        $this->call('generate:model', [
            'name' => $this->getBaseName(),
            $this->force ? '--force' : '--no-force'
        ]);
    }

    /**
     * Creates views for the controller, if requested.
     *
     * @return void
     */
    protected function createViews(): void
    {
        if (!$this->addViews) {
            return;
        }

        $path = $this->getBaseName();
        $force = $this->force ? '--force' : '--no-force';

        $this->call('generate:view', ['name' => path($path, 'index'), $force]);
        $this->call('generate:view', ['name' => path($path, 'create'), $force]);
        $this->call('generate:view', ['name' => path($path, 'show'), $force]);
        $this->call('generate:view', ['name' => path($path, 'update'), $force]);
        $this->call('generate:view', ['name' => path($path, 'destroy'), $force]);

        if (!$this->isApiController()) {
            $this->call('generate:view', ['name' => path($path, 'new'), $force]);
            $this->call('generate:view', ['name' => path($path, 'edit'), $force]);
        }
    }

    /**
     * Defines the base name for the controller.
     *
     * @return string
     */
    protected function getBaseName(): string
    {
        return StringHelper::from($this->controllerName)
            ->removeEnd("Controller")
            ->__toString();
    }
}
