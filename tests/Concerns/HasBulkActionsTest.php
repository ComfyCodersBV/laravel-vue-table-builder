<?php

declare(strict_types=1);

use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class BulkActionsTable extends AbstractTable
{
    public array $handled = [];

    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')
            ->bulkAction(
                label: 'Delete selected',
                each: fn (Product $product) => $product->delete(),
                confirm: true,
            )
            ->bulkAction(
                label: 'Deactivate selected',
                before: fn (array $ids) => $this->handled[] = 'before',
                each: fn (Product $product) => $product->update(['is_active' => false]),
                after: fn (array $ids) => $this->handled[] = 'after',
                requirePassword: true,
            );
    }
}

it('registers bulk actions with incrementing keys', function () {
    $table = (new BulkActionsTable)->make();

    $bulkActions = $table->getBulkActions();

    expect($table->hasBulkActions())->toBeTrue()
        ->and(array_keys($bulkActions))->toBe([0, 1])
        ->and($bulkActions[0]->key)->toBe('0')
        ->and($bulkActions[1]->key)->toBe('1');
});

it('has no bulk actions when none are registered', function () {
    expect(TableBuilder::for([])->hasBulkActions())->toBeFalse();
});

it('knows which table class a bulk action belongs to', function () {
    expect((new BulkActionsTable)->make()->getBulkActions()[0]->tableClass)->toBe(BulkActionsTable::class);
});

it('runs the each callback of a bulk action for the selected rows', function () {
    Product::create(['name' => 'Chair', 'sku' => 'A']);
    Product::create(['name' => 'Table', 'sku' => 'B']);

    (new BulkActionsTable)->performBulkAction(0, [1]);

    expect(Product::pluck('name')->all())->toBe(['Table']);
});

it('runs the before and after callbacks around a bulk action', function () {
    Product::create(['name' => 'Chair', 'sku' => 'A']);

    $configurator = new BulkActionsTable;
    $configurator->performBulkAction(1, [1]);

    expect($configurator->handled)->toBe(['before', 'after'])
        ->and(Product::first()->is_active)->toBeFalse();
});

it('exposes the bulk action payload with a signed url', function () {
    $payload = (new BulkActionsTable)->make()->getBulkActions()[0]->toArray();

    expect($payload)->toHaveKeys(['key', 'label', 'url', 'confirm', 'requirePassword'])
        ->and($payload['url'])->toContain('signature=')
        ->and($payload['confirm'])->toBeTrue();
});
