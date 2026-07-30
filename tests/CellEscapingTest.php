<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

function escapingTableData(array $rows, callable $configure): array
{
    $table = TableBuilder::for($rows);

    $configure($table);

    return $table->toArray()['data'];
}

it('escapes a raw string value of a column without an as closure', function () {
    $data = escapingTableData(
        [['name' => '<b>VETGEDRUKT</b>']],
        fn (TableBuilder $table) => $table->column('name'),
    );

    expect($data[0]['name'])->toBe('&lt;b&gt;VETGEDRUKT&lt;/b&gt;');
});

it('escapes quotes and ampersands that could break out of an attribute', function () {
    $data = escapingTableData(
        [['name' => '" onmouseover="alert(1)" & \'x\'']],
        fn (TableBuilder $table) => $table->column('name'),
    );

    expect($data[0]['name'])
        ->not->toContain('onmouseover="')
        ->toBe('&quot; onmouseover=&quot;alert(1)&quot; &amp; &#039;x&#039;');
});

it('keeps html from an as closure returning an HtmlString', function () {
    $data = escapingTableData(
        [['name' => 'Chair']],
        fn (TableBuilder $table) => $table->column('name', as: fn ($value) => new HtmlString("<em>{$value}</em>")),
    );

    expect($data[0]['name'])->toBe('<em>Chair</em>');
});

it('still escapes a plain string returned from an as closure', function () {
    $data = escapingTableData(
        [['name' => 'Chair']],
        fn (TableBuilder $table) => $table->column('name', as: fn ($value) => "<em>{$value}</em>"),
    );

    expect($data[0]['name'])->toBe('&lt;em&gt;Chair&lt;/em&gt;');
});

it('escapes a nested column value in place without adding keys', function () {
    $data = escapingTableData(
        [['name' => 'Chair', 'category' => ['id' => 3, 'name' => '<script>alert(1)</script>']]],
        fn (TableBuilder $table) => $table->column('category.name'),
    );

    expect($data[0]['category']['name'])->toBe('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($data[0]['category'])->toHaveKeys(['id', 'name'])
        ->and($data[0]['category'])->toHaveCount(2)
        ->and($data[0])->toHaveKeys(['name', 'category']);
});

it('leaves non-string values untouched', function () {
    $data = escapingTableData(
        [['price' => 1250, 'is_active' => true, 'deleted_at' => null, 'tags' => ['a', 'b']]],
        function (TableBuilder $table) {
            $table->column('price')
                ->column('is_active')
                ->column('deleted_at')
                ->column('tags');
        },
    );

    expect($data[0]['price'])->toBe(1250)
        ->and($data[0]['is_active'])->toBeTrue()
        ->and($data[0]['deleted_at'])->toBeNull()
        ->and($data[0]['tags'])->toBe(['a', 'b']);
});

it('does not create a key for a missing nested relationship', function () {
    $data = escapingTableData(
        [['name' => 'Chair', 'category' => null]],
        fn (TableBuilder $table) => $table->column('category.name'),
    );

    expect($data[0]['category'])->toBeNull();
});

it('escapes values of eloquent models too', function () {
    $product = new Product([
        'name' => '<img src=x onerror=alert(1)>',
        'sku' => 'SKU-1',
    ]);

    $data = escapingTableData(
        [$product],
        fn (TableBuilder $table) => $table->column('name'),
    );

    expect($data[0]['name'])->toBe('&lt;img src=x onerror=alert(1)&gt;');
});

it('does not double escape a value that is already escaped by an as closure', function () {
    $data = escapingTableData(
        [['name' => '<b>x</b>']],
        fn (TableBuilder $table) => $table->column('name', as: fn ($value) => new HtmlString(e($value))),
    );

    expect($data[0]['name'])->toBe('&lt;b&gt;x&lt;/b&gt;');
});
