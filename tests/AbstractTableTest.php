<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class ConfiguredProductsTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->class(cell: 'px-2')->paginate(10);
    }
}

class BareTable extends AbstractTable {}

it('builds a query builder table from the resource', function () {
    expect(ConfiguredProductsTable::build())->toBeInstanceOf(QueryBuilder::class);
});

it('applies configure when building', function () {
    expect(ConfiguredProductsTable::build()->toArray()['cellClass'])->toBe('px-2');
});

it('caches the built table instance', function () {
    $configurator = new ConfiguredProductsTable;

    expect($configurator->make())->toBe($configurator->make());
});

it('derives the table name from the configurator', function () {
    expect(ConfiguredProductsTable::build()->toArray()['name'])->toBe('configured_products');
});

it('authorizes any request by default', function () {
    expect((new BareTable)->authorize(Request::create('/')))->toBeTrue();
});

it('falls back to an empty resource', function () {
    expect((new BareTable)->make()->toArray()['data'])->toBe([]);
});

it('ignores a bulk action on a table without a query builder resource', function () {
    (new BareTable)->performBulkAction(0, [1]);
})->throwsNoExceptions();
