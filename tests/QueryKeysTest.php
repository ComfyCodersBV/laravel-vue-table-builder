<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class BookingsQueryKeyTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->selectFilter('is_active', ['0' => 'No', '1' => 'Yes']);
    }
}

it('derives the query keys from the table name by default', function () {
    expect(BookingsQueryKeyTable::build()->queryKeys())->toBe([
        'sort' => 'bookings_query_key_sort',
        'filter' => 'bookings_query_key_filter',
        'page' => 'bookings_query_key_page',
        'perPage' => 'bookings_query_key_perPage',
        'columns' => 'bookings_query_key_columns',
    ]);
});

it('uses unprefixed keys for the default name', function () {
    expect(TableBuilder::for([])->queryKeys()['filter'])->toBe('filter');
});

it('reads the filters from a custom filter query key', function () {
    $request = Request::create('/?f[is_active]=1&filter[is_active]=0');

    $table = new TableBuilder([], $request);
    $table->name('products')->filterQueryKey('f')->selectFilter('is_active', ['0' => 'No', '1' => 'Yes']);

    expect($table->filters()->first()->value)->toBe('1')
        ->and($table->queryKeys()['filter'])->toBe('f')
        ->and($table->queryKeys()['sort'])->toBe('products_sort');
});

it('reads the search from the custom filter query key', function () {
    $request = Request::create('/?f[global]=chair');

    $table = new TableBuilder([], $request);
    $table->name('products')->filterQueryKey('f')->withGlobalSearch();

    expect($table->searchInputs('global')->value)->toBe('chair');
});

it('replaces the prefix of every key with a custom prefix', function () {
    Product::create(['name' => 'Chair', 'sku' => 'A']);
    Product::create(['name' => 'Bench', 'sku' => 'B']);

    $request = Request::create('/?p_sort=-name&products_sort=name');
    app()->instance('request', $request);

    $table = new QueryBuilder(Product::query(), $request);
    $table->name('products')->queryPrefix('p')->column('name', sortable: true);

    expect(array_column($table->toArray()['data'], 'name'))->toBe(['Chair', 'Bench'])
        ->and($table->toArray()['queryKeys']['page'])->toBe('p_page');
});

it('drops the prefix with an empty custom prefix', function () {
    $table = TableBuilder::for([])->name('products')->queryPrefix('');

    expect($table->queryKeys()['filter'])->toBe('filter')
        ->and($table->queryKeys()['page'])->toBe('page');
});

it('uses the custom page key for pagination', function () {
    foreach (range(1, 3) as $number) {
        Product::create(['name' => "Product {$number}", 'sku' => (string) $number]);
    }

    $request = Request::create('/?p_page=2');
    app()->instance('request', $request);
    app()->instance('request', $request);

    $table = new QueryBuilder(Product::query(), $request);
    $table->name('products')->queryPrefix('p')->column('name')->paginate(2);

    expect($table->toArray()['pagination']['current_page'])->toBe(2);
});
