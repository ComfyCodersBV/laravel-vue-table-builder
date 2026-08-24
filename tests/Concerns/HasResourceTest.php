<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

it('marks a row link as a modal', function () {
    $table = TableBuilder::for([['id' => 1]]);
    $table->rowModal(fn (array $row) => ['id' => $row['id']]);

    expect($table->toArray()['rowLinkType'])->toBe('modal');
});

it('marks a row link as a plain href', function () {
    $table = TableBuilder::for([['id' => 1]]);
    $table->rowLink(fn (array $row) => "/products/{$row['id']}", href: true);

    expect($table->toArray()['rowLinkType'])->toBe('href');
});

it('opens a row link in a new tab', function () {
    $table = TableBuilder::for([['id' => 1]]);
    $table->rowLink(fn (array $row) => "/products/{$row['id']}", href: true, newTab: true);

    expect($table->toArray()['rowLinkTarget'])->toBe('_blank');
});

it('resolves row links from paginated items', function () {
    $paginator = new LengthAwarePaginator([['id' => 7], ['id' => 8]], 2, 2, 1);

    $table = TableBuilder::for($paginator);
    $table->rowLink(fn (array $row) => "/products/{$row['id']}");

    expect($table->toArray()['rowLinks'])->toBe(['/products/7', '/products/8']);
});

it('counts the rows of a collection resource', function () {
    $table = TableBuilder::for(collect([['id' => 1], ['id' => 2]]));

    expect($table->totalOnThisPage())->toBe(2)
        ->and($table->totalOnAllPages())->toBe(2)
        ->and($table->isEmpty())->toBeFalse()
        ->and($table->isNotEmpty())->toBeTrue();
});

it('counts the rows of a paginated resource', function () {
    $paginator = new LengthAwarePaginator([['id' => 1], ['id' => 2]], 9, 2, 1);

    $table = TableBuilder::for($paginator);

    expect($table->totalOnThisPage())->toBe(2)
        ->and($table->totalOnAllPages())->toBe(9);
});

it('knows an empty resource', function () {
    expect(TableBuilder::for([])->isEmpty())->toBeTrue();
});

it('finds the primary key of an eloquent row', function () {
    $product = Product::create(['name' => 'Chair', 'sku' => 'SKU-1']);

    expect(TableBuilder::for([])->findPrimaryKey($product))->toBe($product->id);
});

it('finds the primary key through a configured key', function () {
    $table = TableBuilder::for([]);
    $table->primaryKey('sku');

    expect($table->findPrimaryKey(['sku' => 'SKU-9']))->toBe('SKU-9');
});

it('fails to find a primary key on an array without a configured key', function () {
    TableBuilder::for([])->findPrimaryKey(['sku' => 'SKU-9']);
})->throws(Exception::class, 'No primary key configured');

it('collects the primary keys of every row', function () {
    $table = TableBuilder::for(collect([['sku' => 'A'], ['sku' => 'B']]));
    $table->primaryKey('sku');

    expect($table->getPrimaryKeys())->toBe(['A', 'B']);
});
