<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:table')]
class TableMakeCommand extends GeneratorCommand
{
    protected $name = 'make:table';

    protected $type = 'Table';

    protected $description = 'Create a new table class';

    public function handle(): int
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/table.stub');
    }

    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    protected function buildClass($name): array|string
    {
        $tableName = strtolower(class_basename($name));
        $modelName = ucfirst($tableName);

        $replace = [
            '{{ name }}' => $tableName,
            '{{name}}' => $tableName,
            '{{ model }}' => $modelName,
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Tables';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the class already exists'],
        ];
    }
}
