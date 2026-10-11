<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\QueryBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

function releasedProductsTable(string $uri = '/'): QueryBuilder
{
    $request = Request::create($uri);

    app()->instance('request', $request);

    return new QueryBuilder(Product::query(), $request);
}

function releasedNames(QueryBuilder $table): array
{
    return array_column($table->toArray()['data'], 'name');
}

beforeEach(function () {
    Product::create(['name' => 'September', 'sku' => 'S', 'released_on' => '2026-09-20']);
    Product::create(['name' => 'October', 'sku' => 'O', 'released_on' => '2026-10-05']);
    Product::create(['name' => 'November', 'sku' => 'N', 'released_on' => '2026-11-12']);
});

it('filters on a single date', function () {
    $table = releasedProductsTable('/?filter[released_on]=2026-10-05');
    $table->column('name')->dateFilter('released_on');

    expect(releasedNames($table))->toBe(['October']);
});

it('filters on a date range with both ends', function () {
    $table = releasedProductsTable('/?filter[released]=2026-10-01..2026-11-30');
    $table->column('name')->dateRangeFilter('released', column: 'released_on');

    expect(releasedNames($table))->toBe(['October', 'November']);
});

it('filters on a range that is open at the end', function () {
    $table = releasedProductsTable('/?filter[released_on]=2026-10-05..');
    $table->column('name')->dateRangeFilter('released_on');

    expect(releasedNames($table))->toBe(['October', 'November']);
});

it('filters on a range that is open at the start', function () {
    $table = releasedProductsTable('/?filter[released_on]=..2026-10-05');
    $table->column('name')->dateRangeFilter('released_on');

    expect(releasedNames($table))->toBe(['September', 'October']);
});

it('ignores a date that is not a valid Y-m-d date', function () {
    $table = releasedProductsTable('/?filter[released_on]=2026-13-45..nonsense');
    $table->column('name')->dateRangeFilter('released_on');

    expect(releasedNames($table))->toHaveCount(3);
});

it('applies the default range when the query has none', function () {
    $table = releasedProductsTable();
    $table->column('name')->dateRangeFilter('released_on', defaultFrom: '2026-11-01');

    expect(releasedNames($table))->toBe(['November'])
        ->and($table->filters()->first()->value)->toBe('2026-11-01..');
});

it('exposes the date filter types in the payload', function () {
    $table = releasedProductsTable();
    $table->dateFilter('day')->dateRangeFilter('period');

    expect($table->filters()->map->toArray()->pluck('type')->values()->all())->toBe(['date', 'date_range']);
});

it('ignores an array value for a filter', function () {
    $table = releasedProductsTable('/?filter[released_on][from]=2026-10-01');
    $table->column('name')->dateRangeFilter('released_on');

    expect(releasedNames($table))->toHaveCount(3);
});
