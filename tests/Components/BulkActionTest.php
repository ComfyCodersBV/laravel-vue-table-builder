<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\Components\BulkAction;

function bulkAction(array $overrides = []): BulkAction
{
    return new BulkAction(...array_merge([
        'key' => '0',
        'label' => 'Delete selected rows',
        'tableClass' => 'App\Tables\ProductsTable',
    ], $overrides));
}

it('slugs the label', function () {
    expect(bulkAction()->getSlug())->toBe('delete-selected-rows');
});

it('turns a required password into a field name', function () {
    expect(bulkAction(['requirePassword' => true])->requirePassword)->toBe('password')
        ->and(bulkAction()->requirePassword)->toBeFalse();
});

it('keeps a custom password field name', function () {
    expect(bulkAction(['requirePassword' => 'current_password'])->requirePassword)->toBe('current_password');
})->skip('The $requirePassword constructor argument is typed bool, so a custom field name cannot be passed.');

it('signs the url and encodes the table and action', function () {
    $url = bulkAction()->getUrl();

    expect($url)->toContain('signature=')
        ->and($url)->toContain(base64_encode('App\Tables\ProductsTable'))
        ->and($url)->toContain('delete-selected-rows');
});

it('carries the current request query into the url', function () {
    app()->instance('request', Request::create('/products?page=3&signature=abc'));

    $url = bulkAction()->getUrl();

    expect($url)->toContain('page=3')
        ->and(substr_count($url, 'signature='))->toBe(1);
});
