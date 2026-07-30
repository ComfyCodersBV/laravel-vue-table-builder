<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\Components\Column;
use TranquilTools\TableBuilder\Tests\Fixtures\Category;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;
use TranquilTools\TableBuilder\Tests\Fixtures\Variant;

function column(string $key, array|string|null $classes = null, ?Closure $as = null): Column
{
    return new Column(
        key: $key,
        label: 'Label',
        canBeHidden: true,
        hidden: false,
        sortable: false,
        sorted: false,
        highlight: false,
        classes: $classes,
        as: $as,
    );
}

it('flattens nested class arrays into a css class string', function () {
    expect(column('name', [['px-2', 'text-right'], 'font-bold'])->classes)->toBe('px-2 text-right font-bold');
});

it('keeps a class string as given', function () {
    expect(column('name', 'px-2')->classes)->toBe('px-2');
});

it('resolves conditional classes', function () {
    expect(column('name', ['px-2' => true, 'hidden' => false])->classes)->toBe('px-2');
});

it('mixes conditional and plain classes', function () {
    expect(column('name', ['w-16', 'text-right' => true, 'hidden' => false])->classes)->toBe('w-16 text-right');
});

it('resolves conditional classes inside a nested array', function () {
    expect(column('name', [['px-2' => true, 'hidden' => false], 'font-bold'])->classes)->toBe('px-2 font-bold');
});

it('has an empty class string without classes', function () {
    expect(column('name')->classes)->toBe('');
});

it('recognizes a nested key', function () {
    expect(column('category.name')->isNested())->toBeTrue()
        ->and(column('name')->isNested())->toBeFalse();
});

it('splits a nested key into relationship and column', function () {
    $column = column('category.parent.name');

    expect($column->relationshipName())->toBe('category.parent')
        ->and($column->relationshipColumn())->toBe('name');
});

it('reads a plain value from an array row', function () {
    expect(column('name')->getDataFromItem(['name' => 'Chair']))->toBe('Chair');
});

it('reads a nested value from an array row', function () {
    expect(column('category.name')->getDataFromItem(['category' => ['name' => 'Garden']]))->toBe('Garden');
});

it('joins the values of a relationship collection', function () {
    $product = Product::create(['name' => 'Chair', 'sku' => 'A']);
    Variant::create(['product_id' => $product->id, 'name' => 'Red']);
    Variant::create(['product_id' => $product->id, 'name' => 'Blue']);

    expect(column('variants.name')->getDataFromItem($product->fresh()->load('variants')))
        ->toBe("Red\nBlue");
});

it('reads a belongs to value through the relationship', function () {
    $category = Category::create(['name' => 'Garden']);
    $product = Product::create(['name' => 'Chair', 'sku' => 'A', 'category_id' => $category->id]);

    expect(column('category.name')->getDataFromItem($product->fresh()->load('category')))->toBe('Garden');
});

it('falls back to a model accessor for a missing array key', function () {
    $product = Product::create(['name' => 'Chair', 'sku' => 'A']);

    expect(column('label')->getDataFromItem($product))->toBe('Chair (A)');
});

it('returns null for a missing key on an array row', function () {
    expect(column('missing')->getDataFromItem(['name' => 'Chair']))->toBeNull();
});

it('clones every property', function () {
    $original = column('name', 'px-2', fn ($value) => $value);
    $clone = $original->clone();

    expect($clone)->not->toBe($original)
        ->and($clone->key)->toBe($original->key)
        ->and($clone->classes)->toBe($original->classes)
        ->and($clone->as)->toBe($original->as);
});

it('reports sortable as a boolean in the payload', function () {
    $callbackSortable = new Column(
        key: 'name',
        label: 'Name',
        canBeHidden: true,
        hidden: false,
        sortable: fn ($builder, $direction) => $builder,
        sorted: false,
        highlight: false,
    );

    expect($callbackSortable->toArray()['sortable'])->toBeTrue()
        ->and(column('name')->toArray()['sortable'])->toBeFalse();
});
