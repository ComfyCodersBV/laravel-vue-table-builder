<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'make:table')]
class TableMakeCommand extends GeneratorCommand
{
    protected $name = 'make:table';

    protected $type = 'Table';

    protected $description = 'Create a new table class';

    private bool $refused = false;

    public function handle(): ?bool
    {
        $result = parent::handle();

        $this->refused = $result === false && ! $this->option('force');

        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = parent::execute($input, $output);

        return $this->refused ? self::FAILURE : $status;
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/table.stub');
    }

    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
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
        return $rootNamespace.'\Tables';
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the class already exists'],
        ];
    }
}
