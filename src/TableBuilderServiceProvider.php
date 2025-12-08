<?php

namespace TranquilTools\TableBuilder;

use TranquilTools\TableBuilder\Commands\TableMakeCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TableBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-vue-table-builder')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_vue_table_builder_table')
            ->hasCommand(TableMakeCommand::class);
    }
}
