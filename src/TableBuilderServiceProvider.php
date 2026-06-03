<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TranquilTools\TableBuilder\Commands\TableMakeCommand;
use TranquilTools\TableBuilder\TableBuilder;

class TableBuilderServiceProvider extends PackageServiceProvider
{
    public function packageBooted(): void
    {
        TableBuilder::defaultPerPageOptions(config('vue-table-builder.per_page_options', [10, 25, 50, 100]));
        TableBuilder::defaultPerPage(config('vue-table-builder.default_per_page', 25));

        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share('vue_table_builder_table_translations', fn () =>
                trans('vue-table-builder::table')
            );
        }
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-vue-table-builder')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasRoute('web')
            ->hasCommand(TableMakeCommand::class);
    }
}
