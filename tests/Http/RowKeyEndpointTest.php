<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;
use TranquilTools\TableBuilder\Tests\Fixtures\Ticket;

class UuidTicketsTable extends AbstractTable
{
    public function for()
    {
        return Ticket::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('title')
            ->reorderable()
            ->bulkAction(label: 'Close', each: fn (Ticket $ticket) => $ticket->update(['status' => 'closed']));
    }
}

class SkuKeyedProductsTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('name')
            ->rowKey('sku')
            ->bulkAction(label: 'Delete', each: fn (Product $product) => $product->delete());
    }
}

class CodeKeyedRowsTable extends AbstractTable
{
    public function for()
    {
        return DB::table('products');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('name')
            ->rowKey('sku')
            ->bulkAction(label: 'Deactivate', each: fn (object $row) => DB::table('products')->where('sku', $row->sku)->update(['is_active' => false]));
    }
}

function rowKeyBulkUrl(string $tableClass, string $slug): string
{
    return URL::signedRoute('table.bulk-action', [
        'table' => base64_encode($tableClass),
        'action' => base64_encode('0'),
        'slug' => $slug,
    ]);
}

beforeEach(function () {
    Ticket::create(['uuid' => '9b1c3d1e-0000-4000-8000-000000000001', 'title' => 'First', 'sort_order' => 1]);
    Ticket::create(['uuid' => '9b1c3d1e-0000-4000-8000-000000000002', 'title' => 'Second', 'sort_order' => 2]);
    Ticket::create(['uuid' => '9b1c3d1e-0000-4000-8000-000000000003', 'title' => 'Third', 'sort_order' => 3]);

    Product::create(['name' => 'Chair', 'sku' => 'CH-1']);
    Product::create(['name' => 'Table', 'sku' => 'TA-2']);
});

it('sends the key name of the model as the row key', function () {
    $payload = UuidTicketsTable::build()->toArray();

    expect($payload['rowKey'])->toBe('uuid')
        ->and($payload['reorder']['key'])->toBe('uuid');
});

it('runs a bulk action over the posted uuids of a uuid keyed table', function () {
    $this->postJson(rowKeyBulkUrl(UuidTicketsTable::class, 'close'), [
        'ids' => ['9b1c3d1e-0000-4000-8000-000000000002'],
    ])->assertOk();

    expect(Ticket::query()->where('status', 'closed')->pluck('title')->all())->toBe(['Second']);
});

it('runs a bulk action over every row of a uuid keyed table', function () {
    $this->postJson(rowKeyBulkUrl(UuidTicketsTable::class, 'close'), ['ids' => ['*']])->assertOk();

    expect(Ticket::query()->where('status', 'open')->count())->toBe(0);
});

it('reorders the rows of a uuid keyed table by their uuid', function () {
    $this->postJson(URL::signedRoute('table.reorder', ['table' => base64_encode(UuidTicketsTable::class)]), [
        'id' => '9b1c3d1e-0000-4000-8000-000000000003',
        'to' => '9b1c3d1e-0000-4000-8000-000000000001',
    ])->assertOk();

    expect(Ticket::query()->orderBy('sort_order')->pluck('title')->all())->toBe(['Third', 'First', 'Second']);
});

it('matches the posted ids on the configured row key instead of the primary key', function () {
    $payload = SkuKeyedProductsTable::build()->toArray();

    expect($payload['rowKey'])->toBe('sku');

    $this->postJson(rowKeyBulkUrl(SkuKeyedProductsTable::class, 'delete'), ['ids' => ['TA-2']])->assertOk();

    expect(Product::query()->pluck('name')->all())->toBe(['Chair']);
});

it('chunks a plain query table by its row key', function () {
    $this->postJson(rowKeyBulkUrl(CodeKeyedRowsTable::class, 'deactivate'), ['ids' => ['*']])->assertOk();

    expect(Product::query()->where('is_active', true)->count())->toBe(0);
});

it('matches the posted ids of a plain query table on its row key', function () {
    $this->postJson(rowKeyBulkUrl(CodeKeyedRowsTable::class, 'deactivate'), ['ids' => ['CH-1']])->assertOk();

    expect(Product::query()->where('is_active', true)->pluck('name')->all())->toBe(['Table']);
});
