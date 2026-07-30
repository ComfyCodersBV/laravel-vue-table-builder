<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\TableBuilder;

it('registers a select filter with a headline label', function () {
    $table = TableBuilder::for([]);
    $table->selectFilter('is_active', ['1' => 'Active']);

    expect($table->filters()->first()->label)->toBe('Is Active')
        ->and($table->hasFilters())->toBeTrue();
});

it('replaces a filter that is registered again under the same key', function () {
    $table = TableBuilder::for([]);
    $table->selectFilter('is_active', ['1' => 'Active'])
        ->selectFilter('is_active', ['0' => 'Inactive'], label: 'Status');

    expect($table->filters())->toHaveCount(1)
        ->and($table->filters()->first()->label)->toBe('Status');
});

it('takes the filter value from the query string', function () {
    $request = Request::create('/?filter[is_active]=0');

    $table = new TableBuilder([], $request);
    $table->selectFilter('is_active', ['0' => 'Inactive', '1' => 'Active']);

    expect($table->filters()->first()->value)->toBe('0')
        ->and($table->hasFiltersEnabled())->toBeTrue();
});

it('reads the filter value from a namespaced query parameter', function () {
    $request = Request::create('/?products_filter[is_active]=1&filter[is_active]=0');

    $table = new TableBuilder([], $request);
    $table->name('products')->selectFilter('is_active', ['0' => 'Inactive', '1' => 'Active']);

    expect($table->filters()->first()->value)->toBe('1');
});

it('keeps the default value when the query string has no filter', function () {
    $table = TableBuilder::for([]);
    $table->selectFilter('is_active', ['1' => 'Active'], defaultValue: '1');

    expect($table->filters()->first()->value)->toBe('1');
});

it('has no enabled filters without values', function () {
    $table = TableBuilder::for([]);
    $table->selectFilter('is_active', ['1' => 'Active']);

    expect($table->hasFiltersEnabled())->toBeFalse();
});

it('does not leak query values into the registered filters', function () {
    $request = Request::create('/?filter[is_active]=1');

    $table = new TableBuilder([], $request);
    $table->selectFilter('is_active', ['1' => 'Active']);

    $table->filters();

    expect($table->hasFiltersEnabled())->toBeTrue()
        ->and($table->filters()->first()->value)->toBe('1');
});

it('exposes a callback filter as a select in the payload', function () {
    $table = TableBuilder::for([]);
    $table->callbackFilter('cheap', ['yes' => 'Yes'], fn ($builder, $value) => $builder);

    expect($table->filters()->first()->toArray()['type'])->toBe('select')
        ->and($table->filters()->first()->callback)->toBeCallable();
});
