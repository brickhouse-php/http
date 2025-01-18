<?php

namespace Brickhouse\Http\Commands;

use Brickhouse\Console\Attributes\Option;
use Brickhouse\Console\GeneratorCommand;
use Brickhouse\Console\InputOption;
use Brickhouse\Core\AppConfig;
use Brickhouse\Support\StringHelper;

class ControllerGenerator extends GeneratorCommand
{
    /**
     * The type of the class generated.
     *
     * @var string
     */
    public string $type = 'Controller';

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
    public function stub(): string
    {
        if ($this->isApiController()) {
            return __DIR__ . '/../Stubs/Controller.api.stub.php';
        }

        return __DIR__ . '/../Stubs/Controller.stub.php';
    }

    /**
     * @inheritDoc
     */
    public function handle(): int
    {
        $result = parent::handle();

        $this->createModel();
        $this->createViews();

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function defaultNamespace(string $rootNamespace): string
    {
        return $rootNamespace . 'Controllers';
    }

    /**
     * @inheritDoc
     */
    protected function getClass(string $name): string
    {
        return StringHelper::from($name)->end("Controller");
    }

    /**
     * @inheritDoc
     */
    protected function buildStub(string $path, string $name): string
    {
        $content = parent::buildStub($path, $name);

        $modelName = StringHelper::from($name)->removeEnd("Controller");

        $content = str_replace(
            ["ModelNamePlaceholderLowercase", "ModelNamePlaceholder"],
            [strtolower($modelName), $modelName],
            $content
        );

        return $content;
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
            'name' => StringHelper::from($this->className)
                ->removeEnd("Controller")
                ->__toString(),
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

        $path = StringHelper::from($this->className)
            ->removeEnd("Controller")
            ->lower()
            ->__toString();

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
}
