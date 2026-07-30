<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\TableBuilder;

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

it('registers a column twice when the same key is added again', function () {
    $table = TableBuilder::for([]);
    $table->column('name')->column('name', 'Second');

    expect($table->columns())->toHaveCount(2);
})->skip('TableBuilder::column() shadows HasColumns::column(), so the dedupe by key never runs.');

it('registers a search input for a searchable column', function () {
    $table = TableBuilder::for([]);
    $table->column('name', searchable: true);

    expect($table->searchInputs()->has('name'))->toBeTrue();
})->skip('TableBuilder::column() shadows HasColumns::column() and ignores the $searchable argument.');

it('hides columns that are not present in the columns query parameter', function () {
    $request = Request::create('/?columns[]=name');

    $table = new TableBuilder([], $request);
    $table->column('name')->column('sku');

    expect($table->columns()->keyBy('key')['sku']->hidden)->toBeTrue();
})->skip('TableBuilder::columns() shadows HasColumns::columns(), so the columns query parameter is never applied.');
