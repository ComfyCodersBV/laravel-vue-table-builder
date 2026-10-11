<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

function pricedTable(string $uri = '/'): QueryBuilder
{
    $request = Request::create($uri);

    app()->instance('request', $request);

    return (new QueryBuilder(Product::query()->orderBy('id'), $request))
        ->column('name')
        ->column('price')
        ->selectFilter('is_active', ['1' => 'Active', '0' => 'Inactive'])
        ->paginate(2);
}

beforeEach(function () {
    foreach ([['Chair', 10, true], ['Table', 20, true], ['Lamp', 30, false], ['Desk', 40, true]] as [$name, $price, $active]) {
        Product::create(['name' => $name, 'sku' => $name, 'price' => $price, 'is_active' => $active]);
    }
});

it('sums a column over every page of the filtered query', function () {
    $totals = pricedTable()->total('price')->toArray()['totals'];

    expect($totals)->toBe([
        'label' => null,
        'values' => [
            'price' => 100,
        ],
    ]);
});

it('applies the active filters and search to the totals', function () {
    $table = pricedTable('/?filter[is_active]=1')
        ->withGlobalSearch(columns: ['name'])
        ->total('price');

    expect($table->toArray()['totals']['values']['price'])->toBe(70);

    $searched = pricedTable('/?filter[is_active]=1&filter[global]=a')
        ->withGlobalSearch(columns: ['name'])
        ->total('price');

    expect($searched->toArray()['totals']['values']['price'])->toBe(30);
});

it('sums only the rows of the current page when asked', function () {
    $totals = pricedTable('/?page=2')->total('price', pageOnly: true)->toArray()['totals'];

    expect($totals['values']['price'])->toBe(70);
});

it('supports count, avg, min and max', function () {
    $totals = pricedTable()
        ->total('name', 'count')
        ->total('price', 'avg')
        ->total('lowest', 'min', column: 'price')
        ->total('highest', 'max', column: 'price')
        ->toArray()['totals']['values'];

    expect($totals['name'])->toBe(4)
        ->and((float) $totals['price'])->toBe(25.0)
        ->and($totals['lowest'])->toBe(10)
        ->and($totals['highest'])->toBe(40);
});

it('formats a total and escapes the formatted text', function () {
    $totals = pricedTable()
        ->total('price', format: fn (int $value) => '<b>'.number_format($value / 100, 2, ',', '.').'</b>')
        ->total('name', 'count', format: fn (int $count) => new HtmlString("<em>{$count} rows</em>"))
        ->toArray()['totals']['values'];

    expect($totals['price'])->toBe('&lt;b&gt;1,00&lt;/b&gt;')
        ->and($totals['name'])->toBe('<em>4 rows</em>');
});

it('passes the filtered query to a callback total', function () {
    $received = null;

    $totals = pricedTable('/?filter[is_active]=1')
        ->total('price', using: function (Builder $query) use (&$received) {
            $received = $query;

            return $query->sum('price') * 2;
        })
        ->toArray()['totals']['values'];

    expect($totals['price'])->toBe(140)
        ->and($received->getQuery()->orders)->toBeNull();
});

it('passes the rows of the page to a page-only callback total', function () {
    $totals = pricedTable()
        ->total('name', using: fn (Collection $rows) => $rows->pluck('name')->implode(' + '), pageOnly: true)
        ->toArray()['totals']['values'];

    expect($totals['name'])->toBe('Chair + Table');
});

it('sends the label of the totals row', function () {
    expect(pricedTable()->total('price')->totalsLabel('Total')->toArray()['totals']['label'])->toBe('Total');
});

it('sends no totals without a total', function () {
    expect(pricedTable()->toArray()['totals'])->toBeNull();
});

it('totals a collection over every row that matches the search', function () {
    $request = Request::create('/?filter[global]=a');

    app()->instance('request', $request);

    $table = (new TableBuilder(collect([
        ['name' => 'Chair', 'price' => 10],
        ['name' => 'Table', 'price' => 20],
        ['name' => 'Desk', 'price' => 40],
    ]), $request))
        ->withGlobalSearch(columns: ['name'])
        ->column('name')
        ->total('price');

    expect($table->toArray()['totals']['values']['price'])->toBe(30);
});

it('refuses an unknown aggregate', function () {
    pricedTable()->total('price', 'median');
})->throws(InvalidArgumentException::class);

it('gives the filtered and sorted query without pagination', function () {
    $request = Request::create('/?filter[is_active]=1&sort=-price');

    $query = (new QueryBuilder(Product::query(), $request))
        ->column('price', sortable: true)
        ->selectFilter('is_active', ['1' => 'Active'])
        ->paginate(2)
        ->filteredQuery();

    expect($query->pluck('name')->all())->toBe(['Desk', 'Table', 'Chair']);
});

it('reads the filtered query from another request without touching the table', function () {
    $table = pricedTable('/?filter[is_active]=0');

    $query = $table->filteredQuery(Request::create('/?filter[is_active]=1'));

    expect($query->pluck('name')->all())->toBe(['Chair', 'Table', 'Desk'])
        ->and(array_column($table->toArray()['data'], 'name'))->toBe(['Lamp']);
});

it('has no filtered query for a collection table', function () {
    (new TableBuilder(collect()))->filteredQuery();
})->throws(LogicException::class);
