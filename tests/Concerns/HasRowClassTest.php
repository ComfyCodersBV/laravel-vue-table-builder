<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

it('sends the class of each row from the rowClass callback', function () {
    Product::create(['name' => 'Chair', 'sku' => 'A', 'is_active' => false]);
    Product::create(['name' => 'Table', 'sku' => 'B']);

    $rows = TableBuilder::for(Product::query())
        ->column('name')
        ->rowClass(fn (Product $product): string|array => $product->is_active ? ['font-semibold'] : 'text-muted-foreground')
        ->toArray()['data'];

    expect($rows[0]['__class'])->toBe('text-muted-foreground')
        ->and($rows[1]['__class'])->toBe(['font-semibold']);
});

it('leaves out the class of a row when the callback returns nothing', function () {
    $rows = TableBuilder::for(collect([
        ['id' => 1, 'name' => 'Chair'],
        ['id' => 2, 'name' => 'Table'],
    ]))
        ->column('name')
        ->rowClass(fn (array $row): string|array => $row['id'] === 1 ? 'opacity-50' : [
            'font-semibold' => false,
        ])
        ->toArray()['data'];

    expect($rows[0]['__class'])->toBe('opacity-50')
        ->and($rows[1])->not->toHaveKey('__class');
});

it('adds no class to the rows of a table without rowClass', function () {
    Product::create(['name' => 'Chair', 'sku' => 'A']);

    $rows = TableBuilder::for(Product::query())
        ->column('name')
        ->toArray()['data'];

    expect($rows[0])->not->toHaveKey('__class');
});

it('refuses a row that already has a __class value', function () {
    TableBuilder::for(collect([
        ['id' => 1, '__class' => 'from-the-data'],
    ]))
        ->rowClass(fn (array $row): string => 'from-the-table')
        ->toArray();
})->throws(LogicException::class);
