<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class ReorderableProductsTable extends AbstractTable
{
    public function for()
    {
        return Product::query()->where('is_active', true);
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->reorderable('sort_order');
    }
}

class NameTieReorderTable extends AbstractTable
{
    public function for()
    {
        return Product::query()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->reorderable('sort_order');
    }
}

class DefaultSortTieReorderTable extends AbstractTable
{
    public function for()
    {
        return Product::query()->orderBy('sort_order');
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->reorderable('sort_order')->defaultSort('name', 'desc');
    }
}

function seedTiedProducts(): void
{
    foreach (['C', 'A', 'B', 'D'] as $name) {
        Product::create(['name' => $name, 'sku' => $name, 'sort_order' => 0]);
    }
}

function seedOrderedProducts(): void
{
    foreach (['A', 'B', 'C', 'D'] as $index => $name) {
        Product::create(['name' => $name, 'sku' => $name, 'sort_order' => $index + 1]);
    }
}

function productNamesInOrder(): array
{
    return Product::query()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('name')
        ->all();
}

it('moves a row down to the position of the target row', function () {
    seedOrderedProducts();

    (new ReorderableProductsTable)->performReorder(1, 3);

    expect(productNamesInOrder())->toBe(['B', 'C', 'A', 'D']);
});

it('moves a row up to the position of the target row', function () {
    seedOrderedProducts();

    (new ReorderableProductsTable)->performReorder(4, 2);

    expect(productNamesInOrder())->toBe(['A', 'D', 'B', 'C'])
        ->and(Product::query()->orderBy('id')->pluck('sort_order')->all())->toBe([1, 3, 4, 2]);
});

it('only rewrites the rows whose position changed', function () {
    seedOrderedProducts();

    $updates = 0;

    DB::listen(function ($query) use (&$updates) {
        if (str_starts_with(strtolower($query->sql), 'update')) {
            $updates++;
        }
    });

    (new ReorderableProductsTable)->performReorder(2, 3);

    expect($updates)->toBe(2)
        ->and(productNamesInOrder())->toBe(['A', 'C', 'B', 'D']);
});

it('numbers rows that all share the same position', function () {
    foreach (['A', 'B', 'C'] as $name) {
        Product::create(['name' => $name, 'sku' => $name]);
    }

    (new ReorderableProductsTable)->performReorder(3, 1);

    expect(productNamesInOrder())->toBe(['C', 'A', 'B']);
});

it('breaks ties in the order column by the secondary order of the query', function () {
    seedTiedProducts();

    (new NameTieReorderTable)->performReorder(1, 2);

    expect(productNamesInOrder())->toBe(['C', 'A', 'B', 'D'])
        ->and(Product::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2, 3, 4]);
});

it('numbers tied rows in the displayed order on the first move', function () {
    seedTiedProducts();

    (new NameTieReorderTable)->performReorder(4, 2);

    expect(productNamesInOrder())->toBe(['D', 'A', 'B', 'C']);
});

it('breaks ties in the order column by the default sort of the table', function () {
    seedTiedProducts();

    (new DefaultSortTieReorderTable)->performReorder(2, 4);

    expect(productNamesInOrder())->toBe(['A', 'D', 'C', 'B']);
});

it('keeps the order column first when the query also sorts on it', function () {
    seedOrderedProducts();

    Product::query()->where('name', 'A')->update(['sort_order' => 9]);

    (new NameTieReorderTable)->performReorder(2, 3);

    expect(productNamesInOrder())->toBe(['C', 'B', 'D', 'A']);
});

it('refuses a row outside the scope of the table', function () {
    seedOrderedProducts();

    $inactive = Product::create(['name' => 'Hidden', 'sku' => 'X', 'is_active' => false]);

    (new ReorderableProductsTable)->performReorder($inactive->id, 1);
})->throws(InvalidArgumentException::class);

it('refuses to move rows on a table that is not reorderable', function () {
    TableBuilder::for(Product::query())->moveRow(1, 2);
})->throws(LogicException::class);

it('refuses to move rows on a collection table', function () {
    TableBuilder::for([['id' => 1]])->reorderable()->moveRow(1, 2);
})->throws(LogicException::class);

it('sends the reorder column, key and a signed url', function () {
    $reorder = ReorderableProductsTable::build()->toArray()['reorder'];

    expect($reorder['column'])->toBe('sort_order')
        ->and($reorder['key'])->toBe('id')
        ->and($reorder['url'])->toContain('/table/reorder')
        ->and($reorder['url'])->toContain('signature=');
});

it('sends a custom reorder url', function () {
    $table = TableBuilder::for(Product::query())->reorderable('sort_order', url: '/products/reorder');

    expect($table->toArray()['reorder']['url'])->toBe('/products/reorder');
});

it('sends no reorder config by default', function () {
    expect(TableBuilder::for(Product::query())->toArray()['reorder'])->toBeNull();
});

it('reorders the rows of a plain query builder table', function () {
    seedOrderedProducts();

    TableBuilder::for(DB::table('products'))->reorderable()->moveRow(1, 4);

    expect(productNamesInOrder())->toBe(['B', 'C', 'D', 'A']);
});
