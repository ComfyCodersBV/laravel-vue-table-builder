<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\TableBuilder;

afterEach(function () {
    TableBuilder::defaultHighlightFirstColumn(false);
    TableBuilder::defaultColumnCanBeHidden(true);
});

it('labels a column from its key when no label is given', function () {
    $table = TableBuilder::for([]);
    $table->column('first_name');

    expect($table->columns()->first()->label)->toBe('First Name');
});

it('keeps an explicit label', function () {
    $table = TableBuilder::for([]);
    $table->column('first_name', 'Voornaam');

    expect($table->columns()->first()->label)->toBe('Voornaam');
});

it('lists the columns that are visible by default', function () {
    $table = TableBuilder::for([]);
    $table->column('name')
        ->column('sku', hidden: true)
        ->column('price', canBeHidden: false);

    expect($table->defaultVisibleToggleableColumns())->toBe(['name', 'price']);
});

it('knows whether any column can be hidden', function () {
    $withToggleable = TableBuilder::for([]);
    $withToggleable->column('name');

    $withoutToggleable = TableBuilder::for([]);
    $withoutToggleable->column('name', canBeHidden: false);

    expect($withToggleable->hasToggleableColumns())->toBeTrue()
        ->and($withoutToggleable->hasToggleableColumns())->toBeFalse();
});

it('marks only the sorted column as sorted', function () {
    $request = Request::create('/?sort=-sku');

    $table = new TableBuilder([], $request);
    $table->column('name', sortable: true)->column('sku', sortable: true);

    $columns = $table->columns()->keyBy('key');

    expect($columns['sku']->sorted)->toBe('desc')
        ->and($columns['name']->sorted)->toBeFalse();
});

it('exposes sortable and alignment in the column payload', function () {
    $table = TableBuilder::for([]);
    $table->column('name', sortable: true, alignment: 'right', clickable: false);

    expect($table->columns()->first()->toArray())
        ->toMatchArray([
            'key' => 'name',
            'sortable' => true,
            'alignment' => 'right',
            'clickable' => false,
        ]);
});

it('replaces a column when the same key is added again', function () {
    $table = TableBuilder::for([]);
    $table->column('name')->column('name', 'Second');

    expect($table->columns())->toHaveCount(1)
        ->and($table->columns()->first()->label)->toBe('Second');
});

it('registers a search input for a searchable column', function () {
    $table = TableBuilder::for([]);
    $table->column('name', searchable: true);

    expect($table->searchInputs()->has('name'))->toBeTrue();
});

it('hides columns that are not present in the columns query parameter', function () {
    $request = Request::create('/?columns[]=name');

    $table = new TableBuilder([], $request);
    $table->column('name')->column('sku');

    expect($table->columns()->keyBy('key')['sku']->hidden)->toBeTrue()
        ->and($table->columns()->keyBy('key')['name']->hidden)->toBeFalse();
});

it('ignores the columns query parameter for a column that cannot be hidden', function () {
    $request = Request::create('/?columns[]=name');

    $table = new TableBuilder([], $request);
    $table->column('name')->column('sku', canBeHidden: false);

    expect($table->columns()->keyBy('key')['sku']->hidden)->toBeFalse();
});

it('derives a column key from its label', function () {
    $table = TableBuilder::for([]);
    $table->column(label: 'First Name');

    expect($table->columns()->first()->key)->toBe('first-name');
});

it('labels a nested column key as a readable headline', function () {
    $table = TableBuilder::for([]);
    $table->column('category.name');

    expect($table->columns()->first()->label)->toBe('Category Name');
});

it('carries column classes into the payload', function () {
    $table = TableBuilder::for([]);
    $table->column('name', classes: ['w-16', 'text-right']);

    expect($table->columns()->first()->toArray()['class'])->toBe('w-16 text-right');
});

it('highlights the first column when that default is enabled', function () {
    TableBuilder::defaultHighlightFirstColumn(true);

    $table = TableBuilder::for([]);
    $table->column('name')->column('sku');

    $columns = $table->columns()->keyBy('key');

    expect($columns['name']->highlight)->toBeTrue()
        ->and($columns['sku']->highlight)->toBeFalse();
});

it('applies the default for hideable columns', function () {
    TableBuilder::defaultColumnCanBeHidden(false);

    $table = TableBuilder::for([]);
    $table->column('name');

    expect($table->hasToggleableColumns())->toBeFalse();
});

it('lets an explicit highlight win over the default', function () {
    TableBuilder::defaultHighlightFirstColumn(true);

    $table = TableBuilder::for([]);
    $table->column('name', highlight: false);

    expect($table->columns()->first()->highlight)->toBeFalse();
});

it('marks the default sorted column as sorted in the payload', function () {
    $table = TableBuilder::for([]);
    $table->column('price', sortable: true)->defaultSortDesc('price');

    expect($table->columns()->first()->sorted)->toBe('desc');
});
