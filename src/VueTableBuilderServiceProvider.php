<?php

declare(strict_types=1);

namespace TranquilTools\VueTableBuilder;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use TranquilTools\TableBuilder\Support\TableTranslations;

class VueTableBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('vue_table_builder_table_translations', fn (): array => TableTranslations::all());
    }
}
