<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TranquilTools\TableBuilder\Commands\TableMakeCommand;

class TableBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vue-table-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_vue_table_builder_table')
            ->hasCommand(TableMakeCommand::class);
    }
}
