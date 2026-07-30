<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Exceptions\PaginationException;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class SampleProductsTable extends AbstractTable
{
    public function for(): array
    {
        return [];
    }
}

it('wraps an eloquent model class in a query builder', function () {
    expect(TableBuilder::for(Product::class))->toBeInstanceOf(QueryBuilder::class);
});

it('wraps an eloquent builder in a query builder', function () {
    expect(TableBuilder::for(Product::query()))->toBeInstanceOf(QueryBuilder::class);
});

it('wraps a relation in a query builder', function () {
    $product = Product::create(['name' => 'Chair', 'sku' => 'SKU-1']);

    expect(TableBuilder::for($product->variants()))->toBeInstanceOf(QueryBuilder::class);
});

it('keeps an array resource on the plain table builder', function () {
    expect(TableBuilder::for([['id' => 1]]))->toBeInstanceOf(TableBuilder::class)
        ->not->toBeInstanceOf(QueryBuilder::class);
});

it('exposes the configured payload keys', function () {
    $table = TableBuilder::for([['name' => 'Chair']]);

    $table->column('name')->class(cell: 'px-2', head: 'font-bold');

    expect($table->toArray())->toHaveKeys([
        'name',
        'data',
        'columns',
        'pagination',
        'filters',
        'searchInputs',
        'perPageOptions',
        'defaultSort',
        'bulkActions',
        'rowLinks',
        'rowLinkType',
        'cellClass',
        'headClass',
    ])
        ->and($table->toArray()['cellClass'])->toBe('px-2')
        ->and($table->toArray()['headClass'])->toBe('font-bold');
});

it('serializes to json through the array payload', function () {
    $table = TableBuilder::for([['name' => 'Chair']]);

    expect($table->jsonSerialize())->toEqual($table->toArray());
});

it('extracts pagination data from a paginated resource', function () {
    $paginator = new LengthAwarePaginator(
        [['name' => 'Chair'], ['name' => 'Table']],
        12,
        2,
        2,
        ['path' => 'http://localhost/products'],
    );

    $table = TableBuilder::for($paginator);
    $table->column('name');

    $payload = $table->toArray();

    expect($payload['pagination']['current_page'])->toBe(2)
        ->and($payload['pagination']['total'])->toBe(12)
        ->and($payload['pagination']['per_page'])->toBe(2)
        ->and($payload['pagination']['last_page'])->toBe(6)
        ->and($payload['data'])->toHaveCount(2);
});

it('leaves pagination null for a non paginated resource', function () {
    expect(TableBuilder::for([['name' => 'Chair']])->toArray()['pagination'])->toBeNull();
});

it('resolves a row link per row', function () {
    $table = TableBuilder::for([['id' => 1], ['id' => 2]]);

    $table->rowLink(fn (array $row) => "/products/{$row['id']}");

    $payload = $table->toArray();

    expect($payload['rowLinks'])->toBe(['/products/1', '/products/2'])
        ->and($payload['rowLinkType'])->toBe('link');
});

it('has no row links when no callback is configured', function () {
    expect(TableBuilder::for([['id' => 1]])->toArray()['rowLinks'])->toBe([]);
});

it('namespaces query parameters with the table name', function () {
    $request = Request::create('/?products_sort=-name&sort=sku');

    $table = new TableBuilder([['name' => 'Chair']], $request);
    $table->name('products')->column('name', sortable: true)->column('sku', sortable: true);

    $columns = $table->columns()->keyBy('key');

    expect($columns['name']->sorted)->toBe('desc')
        ->and($columns['sku']->sorted)->toBeFalse();
});

it('reads unprefixed query parameters for the default table name', function () {
    $request = Request::create('/?sort=name');

    $table = new TableBuilder([['name' => 'Chair']], $request);
    $table->column('name', sortable: true);

    expect($table->columns()->first()->sorted)->toBe('asc')
        ->and($table->isSorted())->toBeTrue();
});

it('is not sorted without a sort query parameter', function () {
    expect(TableBuilder::for([])->isSorted())->toBeFalse();
});

it('derives the table name from the configurator class', function () {
    $table = TableBuilder::for([]);
    $table->setConfigurator(new SampleProductsTable);

    expect($table->toArray()['name'])->toBe('sample_products');
});

it('keeps an explicitly configured name when a configurator is set', function () {
    $table = TableBuilder::for([]);
    $table->name('custom')->setConfigurator(new SampleProductsTable);

    expect($table->toArray()['name'])->toBe('custom');
});

it('normalizes the default sort direction', function () {
    expect(TableBuilder::for([])->defaultSort('name')->getDefaultSort())->toBe('name')
        ->and(TableBuilder::for([])->defaultSort('name', 'desc')->getDefaultSort())->toBe('-name')
        ->and(TableBuilder::for([])->defaultSort('-name')->getDefaultSort())->toBe('-name')
        ->and(TableBuilder::for([])->defaultSortDesc('name')->getDefaultSort())->toBe('-name');
});

it('rejects an unknown default sort direction', function () {
    TableBuilder::for([])->defaultSort('name', 'sideways');
})->throws(InvalidArgumentException::class);

it('refuses pagination calls on a non query resource', function () {
    TableBuilder::for([['id' => 1]])->paginate(10);
})->throws(PaginationException::class);

it('refuses simple pagination calls on a non query resource', function () {
    TableBuilder::for([['id' => 1]])->simplePaginate(10);
})->throws(PaginationException::class);

it('refuses cursor pagination calls on a non query resource', function () {
    TableBuilder::for([['id' => 1]])->cursorPaginate(10);
})->throws(PaginationException::class);

it('sorts the per page options and includes the current value', function () {
    $table = TableBuilder::for([]);
    $table->perPageOptions([25, 10]);

    expect(array_values($table->allPerPageOptions()))->toBe([10, 25]);
});

it('filters a collection resource with a search input value', function () {
    $request = Request::create('/?filter[name]=chair');

    $table = new TableBuilder(collect([
        ['name' => 'Chair'],
        ['name' => 'Table'],
    ]), $request);

    $table->column('name')->searchInput('name');

    $data = $table->toArray()['data'];

    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Chair');
});

it('matches a collection resource search case insensitively on nested keys', function () {
    $request = Request::create('/?filter[category-name]=GARDEN');

    $table = new TableBuilder(collect([
        ['name' => 'Chair', 'category' => ['name' => 'Garden']],
        ['name' => 'Table', 'category' => ['name' => 'Indoor']],
    ]), $request);

    $table->searchInput(key: 'category-name', columns: ['category.name']);

    expect($table->toArray()['data'])->toHaveCount(1);
});

it('loads the resource only once', function () {
    $table = TableBuilder::for(collect([['name' => 'Chair']]));

    expect($table->loadResource())->toBe($table->loadResource());
});
