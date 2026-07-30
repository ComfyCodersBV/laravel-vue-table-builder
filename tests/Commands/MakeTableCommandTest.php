<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(app_path('Tables/ProductsTable.php'));
});

it('generates a table class', function () {
    $this->artisan('make:table', ['name' => 'ProductsTable'])->assertSuccessful();

    $path = app_path('Tables/ProductsTable.php');

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('class ProductsTable extends AbstractTable');
});

it('fills the model name into the generated class', function () {
    $this->artisan('make:table', ['name' => 'ProductsTable'])->assertSuccessful();

    expect(File::get(app_path('Tables/ProductsTable.php')))
        ->toContain('use App\Models\Productstable;')
        ->toContain('return Productstable::query();');
});

it('refuses to overwrite an existing table class', function () {
    $this->artisan('make:table', ['name' => 'ProductsTable'])->assertSuccessful();
    $this->artisan('make:table', ['name' => 'ProductsTable'])->assertFailed();
});

it('overwrites an existing table class when forced', function () {
    $this->artisan('make:table', ['name' => 'ProductsTable'])->assertSuccessful();
    $this->artisan('make:table', ['name' => 'ProductsTable', '--force' => true])->assertSuccessful();
});
