<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\Components\SearchInput;
use TranquilTools\TableBuilder\Exceptions\PowerJoinsException;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Category;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

function seedProducts(): void
{
    $garden = Category::create(['name' => 'Garden']);
    $indoor = Category::create(['name' => 'Indoor']);

    Product::create(['name' => 'Chair', 'sku' => 'AAA-1', 'price' => 30, 'is_active' => true, 'category_id' => $garden->id]);
    Product::create(['name' => 'Table', 'sku' => 'BBB-2', 'price' => 20, 'is_active' => false, 'category_id' => $indoor->id]);
    Product::create(['name' => 'Bench', 'sku' => 'CCC-3', 'price' => 10, 'is_active' => true, 'category_id' => $garden->id]);
}

function productTable(string $uri = '/'): QueryBuilder
{
    $request = Request::create($uri);

    app()->instance('request', $request);

    return new QueryBuilder(Product::query(), $request);
}

function tableColumn(array $data, string $key): array
{
    return array_column($data, $key);
}

beforeEach(function () {
    seedProducts();
});

it('sorts ascending on a sort query parameter', function () {
    $table = productTable('/?sort=price');
    $table->column('price', sortable: true);

    expect(tableColumn($table->toArray()['data'], 'price'))->toBe([10, 20, 30]);
});

it('sorts descending on a prefixed sort query parameter', function () {
    $table = productTable('/?sort=-price');
    $table->column('price', sortable: true);

    expect(tableColumn($table->toArray()['data'], 'price'))->toBe([30, 20, 10]);
});

it('applies the default sort when nothing is sorted', function () {
    $table = productTable();
    $table->column('price', sortable: true)->defaultSortDesc('price');

    expect(tableColumn($table->toArray()['data'], 'price'))->toBe([30, 20, 10]);
});

it('lets an explicit sort win over the default sort', function () {
    $table = productTable('/?sort=price');
    $table->column('price', sortable: true)->defaultSortDesc('price');

    expect(tableColumn($table->toArray()['data'], 'price'))->toBe([10, 20, 30]);
});

it('sorts with a custom sortable callback', function () {
    $table = productTable('/?sort=label');
    $table->column('label', sortable: fn ($builder, $direction) => $builder->orderBy('sku', $direction));

    expect(tableColumn($table->toArray()['data'], 'sku'))->toBe(['AAA-1', 'BBB-2', 'CCC-3']);
});

it('passes the sort direction to a sortable callback', function () {
    $table = productTable('/?sort=-label');
    $table->column('label', sortable: fn ($builder, $direction) => $builder->orderBy('sku', $direction));

    expect(tableColumn($table->toArray()['data'], 'sku'))->toBe(['CCC-3', 'BBB-2', 'AAA-1']);
});

it('requires the power joins package to sort on a relationship column', function () {
    $table = productTable('/?sort=category.name');
    $table->column('category.name', sortable: true);

    $table->toArray();
})->throws(PowerJoinsException::class);

it('eager loads relationships for nested columns', function () {
    $table = productTable();
    $table->column('category.name');

    $data = $table->toArray()['data'];

    expect($data[0]['category']['name'])->toBe('Garden');
});

it('filters results with a wildcard search input', function () {
    $table = productTable('/?filter[name]=ai');
    $table->column('name')->searchInput('name');

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair']);
});

it('filters results with an exact search method', function () {
    $table = productTable('/?filter[sku]=AAA-1');
    $table->column('sku')->searchInput(key: 'sku', columns: ['sku' => SearchInput::EXACT]);

    expect($table->toArray()['data'])->toHaveCount(1);
});

it('finds nothing for a partial term when the search method is exact', function () {
    $table = productTable('/?filter[sku]=AAA');
    $table->column('sku')->searchInput(key: 'sku', columns: ['sku' => SearchInput::EXACT]);

    expect($table->toArray()['data'])->toBeEmpty();
});

it('searches across multiple columns of one input', function () {
    $table = productTable('/?filter[global]=BBB');
    $table->column('name')->withGlobalSearch(columns: ['name', 'sku']);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Table']);
});

it('searches through a relationship column', function () {
    $table = productTable('/?filter[category-name]=Indoor');
    $table->column('name')->searchInput(key: 'category-name', columns: ['category.name']);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Table']);
});

it('splits a search term into separate terms by default', function () {
    $table = productTable('/?filter[global]=Chair Bench');
    $table->column('name')->withGlobalSearch(columns: ['name'])->paginate(10);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair', 'Bench']);
});

it('keeps a quoted search phrase together', function () {
    $table = productTable('/?filter[global]="Chair Bench"');
    $table->column('name')->withGlobalSearch(columns: ['name'])->paginate(10);

    expect($table->toArray()['data'])->toBeEmpty();
});

it('treats the whole term as one when term parsing is disabled', function () {
    $table = productTable('/?filter[global]=Chair Bench');
    $table->column('name')->withGlobalSearch(columns: ['name'])->parseTerms(false)->paginate(10);

    expect($table->toArray()['data'])->toBeEmpty();
});

it('does not re-filter query builder results in memory', function () {
    $table = productTable('/?filter[global]=Chair Bench');
    $table->column('name')->withGlobalSearch(columns: ['name']);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair', 'Bench']);
});

it('filters an unpaginated query builder once on a single term', function () {
    $table = productTable('/?filter[global]=Chair');
    $table->column('name')->withGlobalSearch(columns: ['name']);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair']);
});

it('filters a paginated query builder on multiple terms', function () {
    $table = productTable('/?filter[global]=Chair Bench');
    $table->column('name')->withGlobalSearch(columns: ['name'])->perPageOptions([10])->paginate(10);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair', 'Bench']);
});

it('parses terms into a collection without empty values', function () {
    expect(productTable()->parseTermsIntoCollection('  chair   table ')->all())->toBe(['chair', 'table']);
});

it('narrows results with a select filter', function () {
    $table = productTable('/?filter[is_active]=1');
    $table->column('name')->selectFilter('is_active', ['1' => 'Active', '0' => 'Inactive']);

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair', 'Bench']);
});

it('matches a text filter on a partial, case insensitive value', function () {
    $table = productTable('/?filter[name]=ai');
    $table->column('name')->textFilter('name');

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Chair']);
});

it('keeps a select filter on an exact value', function () {
    $table = productTable('/?filter[name]=Chai');
    $table->column('name')->selectFilter('name', ['Chair' => 'Chair']);

    expect($table->toArray()['data'])->toBeEmpty();
});

it('applies a callback filter through its callback', function () {
    $table = productTable('/?filter[cheap]=yes');
    $table->column('name')->callbackFilter(
        key: 'cheap',
        options: ['yes' => 'Yes'],
        callback: fn ($builder, $value) => $builder->where('price', '<', 25),
    );

    expect(tableColumn($table->toArray()['data'], 'name'))->toBe(['Table', 'Bench']);
});

it('ignores a filter without a value', function () {
    $table = productTable();
    $table->column('name')->selectFilter('is_active', ['1' => 'Active']);

    expect($table->toArray()['data'])->toHaveCount(3);
});

it('paginates the results', function () {
    $table = productTable();
    $table->column('name')->perPageOptions([2])->paginate(2);

    $payload = $table->toArray();

    expect($payload['data'])->toHaveCount(2)
        ->and($payload['pagination']['total'])->toBe(3)
        ->and($payload['pagination']['per_page'])->toBe(2)
        ->and($payload['pagination']['last_page'])->toBe(2);
});

it('reads the page from a namespaced query parameter', function () {
    $table = productTable('/?products_page=2');
    $table->name('products')->column('name')->perPageOptions([2])->paginate(2);

    $payload = $table->toArray();

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['pagination']['current_page'])->toBe(2);
});

it('accepts a per page value from the query when it is an allowed option', function () {
    $table = productTable('/?perPage=1');
    $table->column('name')->perPageOptions([1, 2])->paginate(2);

    expect($table->toArray()['pagination']['per_page'])->toBe(1);
});

it('falls back to the configured per page for a value outside the options', function () {
    $table = productTable('/?perPage=999');
    $table->column('name')->perPageOptions([1, 2])->paginate(2);

    expect($table->toArray()['pagination']['per_page'])->toBe(2);
});

it('returns every row without pagination', function () {
    $table = productTable();
    $table->column('name')->paginate(2)->noPagination();

    $payload = $table->toArray();

    expect($payload['data'])->toHaveCount(3)
        ->and($payload['pagination'])->toBeNull();
});

it('runs a bulk action for the given ids', function () {
    $table = productTable();
    $table->column('name');

    $names = [];

    $table->performBulkAction(function (Product $product) use (&$names) {
        $names[] = $product->name;
    }, [1, 3]);

    expect($names)->toBe(['Chair', 'Bench']);
});

it('runs a bulk action for every row when all rows are selected', function () {
    $table = productTable();
    $table->column('name');

    $count = 0;

    $table->performBulkAction(function (Product $product) use (&$count) {
        $count++;
    }, ['*']);

    expect($count)->toBe(3);
});

it('respects filters when a bulk action targets all rows', function () {
    $table = productTable('/?filter[is_active]=1');
    $table->column('name')->selectFilter('is_active', ['1' => 'Active']);

    $names = [];

    $table->performBulkAction(function (Product $product) use (&$names) {
        $names[] = $product->name;
    }, ['*']);

    expect($names)->toBe(['Chair', 'Bench']);
});
