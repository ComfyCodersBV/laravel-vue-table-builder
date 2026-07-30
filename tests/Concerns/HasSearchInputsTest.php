<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\Components\SearchInput;
use TranquilTools\TableBuilder\TableBuilder;

afterEach(function () {
    TableBuilder::defaultGlobalSearch(false);
});

it('searches the key column when no columns are given', function () {
    $table = TableBuilder::for([]);
    $table->searchInput('name');

    expect($table->searchInputs('name')->columns)->toBe(['name' => null]);
});

it('builds a slug key for an array of columns', function () {
    $table = TableBuilder::for([]);
    $table->searchInput(['sku', 'name']);

    $searchInput = $table->searchInputs()->first();

    expect($searchInput->key)->toBe('name-sku')
        ->and(array_keys($searchInput->columns))->toBe(['name', 'sku']);
});

it('keeps a per column search method', function () {
    $table = TableBuilder::for([]);
    $table->searchInput(key: 'sku', columns: ['sku' => SearchInput::EXACT, 'name']);

    expect($table->searchInputs('sku')->columns)->toBe([
        'sku' => SearchInput::EXACT,
        'name' => null,
    ]);
});

it('replaces a search input registered again under the same key', function () {
    $table = TableBuilder::for([]);
    $table->searchInput('name')->searchInput('name', label: 'Naam');

    expect($table->searchInputs())->toHaveCount(1)
        ->and($table->searchInputs('name')->label)->toBe('Naam');
});

it('takes the search value from the query string', function () {
    $request = Request::create('/?filter[name]=chair');

    $table = new TableBuilder([], $request);
    $table->searchInput('name');

    expect($table->searchInputs('name')->value)->toBe('chair')
        ->and($table->hasSearchFiltersEnabled())->toBeTrue();
});

it('adds and removes a global search input', function () {
    $table = TableBuilder::for([]);
    $table->withGlobalSearch();

    expect($table->searchInputs()->has(TableBuilder::GLOBAL_SEARCH_KEY))->toBeTrue();

    $table->withoutGlobalSearch();

    expect($table->searchInputs())->toBeEmpty();
});

it('does not count the global search input as toggleable', function () {
    $table = TableBuilder::for([]);
    $table->withGlobalSearch();

    expect($table->hasToggleableSearchInputs())->toBeFalse();

    $table->searchInput('name');

    expect($table->hasToggleableSearchInputs())->toBeTrue();
});

it('adds a global search input to new tables when enabled by default', function () {
    TableBuilder::defaultGlobalSearch('Zoeken');

    expect(TableBuilder::for([])->searchInputs()->has(TableBuilder::GLOBAL_SEARCH_KEY))->toBeTrue();
});

it('has no search filters enabled without values', function () {
    $table = TableBuilder::for([]);
    $table->searchInput('name');

    expect($table->hasSearchFiltersEnabled())->toBeFalse();
});
