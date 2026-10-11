<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

it('resolves the row actions for every row', function () {
    $table = TableBuilder::for([
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second'],
    ]);

    $table
        ->column('name')
        ->rowAction('Edit', url: fn (array $row) => "/items/{$row['id']}/edit")
        ->rowAction('Delete', url: fn (array $row) => "/items/{$row['id']}", method: 'DELETE', confirm: 'Sure?', variant: 'destructive');

    expect($table->toArray()['rowActions'])->toBe([
        [
            ['key' => 'edit', 'label' => 'Edit', 'url' => '/items/1/edit', 'method' => 'get', 'confirm' => null, 'variant' => 'outline', 'disabled' => false, 'disabledReason' => null],
            ['key' => 'delete', 'label' => 'Delete', 'url' => '/items/1', 'method' => 'delete', 'confirm' => 'Sure?', 'variant' => 'destructive', 'disabled' => false, 'disabledReason' => null],
        ],
        [
            ['key' => 'edit', 'label' => 'Edit', 'url' => '/items/2/edit', 'method' => 'get', 'confirm' => null, 'variant' => 'outline', 'disabled' => false, 'disabledReason' => null],
            ['key' => 'delete', 'label' => 'Delete', 'url' => '/items/2', 'method' => 'delete', 'confirm' => 'Sure?', 'variant' => 'destructive', 'disabled' => false, 'disabledReason' => null],
        ],
    ]);
});

it('leaves out an action that is not visible for a row', function () {
    $table = TableBuilder::for([
        ['id' => 1, 'locked' => false],
        ['id' => 2, 'locked' => true],
    ]);

    $table->rowAction('Edit', url: '/edit', visible: fn (array $row) => ! $row['locked']);

    $rowActions = $table->toArray()['rowActions'];

    expect($rowActions[0])->toHaveCount(1)
        ->and($rowActions[1])->toBe([]);
});

it('leaves out an action whose url resolves to null', function () {
    $table = TableBuilder::for([['id' => 1]]);

    $table->rowAction('Edit', url: fn () => null);

    expect($table->toArray()['rowActions'])->toBe([[]]);
});

it('disables an action with a reason', function () {
    $table = TableBuilder::for([
        ['id' => 1, 'units' => 0],
        ['id' => 2, 'units' => 3],
    ]);

    $table->rowAction('Delete', url: '/delete', method: 'delete', disabled: fn (array $row) => $row['units'] > 0 ? 'Still has units' : false);

    $rowActions = $table->toArray()['rowActions'];

    expect($rowActions[0][0]['disabled'])->toBeFalse()
        ->and($rowActions[0][0]['disabledReason'])->toBeNull()
        ->and($rowActions[1][0]['disabled'])->toBeTrue()
        ->and($rowActions[1][0]['disabledReason'])->toBe('Still has units');
});

it('disables an action without a reason', function () {
    $table = TableBuilder::for([['id' => 1]]);

    $table->rowAction('Delete', url: '/delete', disabled: true);

    expect($table->toArray()['rowActions'][0][0])
        ->toMatchArray(['disabled' => true, 'disabledReason' => null]);
});

it('resolves a named route with the row as parameter', function () {
    Route::get('products/{product}/edit', fn () => '')->name('products.edit');
    app('router')->getRoutes()->refreshNameLookups();

    $product = Product::create(['name' => 'Chair', 'sku' => 'AAA-1']);

    $table = TableBuilder::for(Product::query());

    $table->column('name')->rowAction('Edit', route: 'products.edit');

    expect($table->toArray()['rowActions'][0][0]['url'])->toEndWith("/products/{$product->id}/edit");
});

it('rejects an action without url or route', function () {
    TableBuilder::for([])->rowAction('Edit');
})->throws(InvalidArgumentException::class);

it('rejects an unknown method', function () {
    TableBuilder::for([])->rowAction('Edit', url: '/edit', method: 'options');
})->throws(InvalidArgumentException::class);

it('sends no row actions when none are defined', function () {
    $table = TableBuilder::for([['id' => 1]]);

    expect($table->toArray()['rowActions'])->toBe([]);
});

it('passes the model to the callbacks of a query table', function () {
    Product::create(['name' => 'Chair', 'sku' => 'AAA-1']);

    $table = TableBuilder::for(Product::query());

    $table->column('name')->rowAction('Edit', url: fn (Product $product) => "/products/{$product->id}");

    expect($table->toArray()['rowActions'][0][0]['url'])->toBe('/products/1');
});

it('marks a download action and keeps the payload of other actions unchanged', function () {
    $table = TableBuilder::for([['id' => 1]]);

    $table
        ->rowAction('Edit', url: '/edit')
        ->rowAction('PDF', url: fn (array $row) => "/items/{$row['id']}/pdf", download: true)
        ->rowAction('CSV', url: '/export.csv', download: 'items.csv');

    [$edit, $pdf, $csv] = $table->toArray()['rowActions'][0];

    expect($edit)->not->toHaveKey('download')
        ->and($pdf['download'])->toBeTrue()
        ->and($pdf['url'])->toBe('/items/1/pdf')
        ->and($csv['download'])->toBe('items.csv');
});

it('refuses a download action with another method than get', function () {
    TableBuilder::for([])->rowAction('PDF', url: '/pdf', method: 'post', download: true);
})->throws(InvalidArgumentException::class);
