<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\UnauthorizedException;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class EndpointProductsTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->bulkAction(
            label: 'Delete selected',
            each: fn (Product $product) => $product->delete(),
        );
    }
}

class ForbiddenProductsTable extends EndpointProductsTable
{
    public function authorize(Request $request): bool
    {
        return false;
    }
}

function bulkActionUrl(string $tableClass): string
{
    return URL::signedRoute('table.bulk-action', [
        'table' => base64_encode($tableClass),
        'action' => base64_encode('0'),
        'slug' => 'delete-selected',
    ]);
}

beforeEach(function () {
    Product::create(['name' => 'Chair', 'sku' => 'A']);
    Product::create(['name' => 'Table', 'sku' => 'B']);
});

it('performs the bulk action for the posted ids', function () {
    $this->post(bulkActionUrl(EndpointProductsTable::class), ['ids' => [1]])
        ->assertRedirect();

    expect(Product::pluck('name')->all())->toBe(['Table']);
});

it('performs the bulk action for every row when all rows are selected', function () {
    $this->post(bulkActionUrl(EndpointProductsTable::class), ['ids' => ['*']])
        ->assertRedirect();

    expect(Product::count())->toBe(0);
});

it('rejects an unsigned request', function () {
    $this->post(route('table.bulk-action', [
        'table' => base64_encode(EndpointProductsTable::class),
        'action' => base64_encode('0'),
        'slug' => 'delete-selected',
    ]), ['ids' => [1]])->assertForbidden();

    expect(Product::count())->toBe(2);
});

it('rejects a tampered signature', function () {
    $this->post(bulkActionUrl(EndpointProductsTable::class).'x', ['ids' => [1]])
        ->assertForbidden();

    expect(Product::count())->toBe(2);
});

it('rejects a request whose query was changed after signing', function () {
    $url = bulkActionUrl(EndpointProductsTable::class);

    $this->post($url.'&extra=1', ['ids' => [1]])->assertForbidden();

    expect(Product::count())->toBe(2);
});

it('rejects another table smuggled into a signed url', function () {
    $url = str_replace(
        base64_encode(EndpointProductsTable::class),
        base64_encode(ForbiddenProductsTable::class),
        bulkActionUrl(EndpointProductsTable::class),
    );

    $this->post($url, ['ids' => [1]])->assertForbidden();

    expect(Product::count())->toBe(2);
});

it('rejects an expired temporary signature', function () {
    $url = URL::temporarySignedRoute('table.bulk-action', now()->subMinute(), [
        'table' => base64_encode(EndpointProductsTable::class),
        'action' => base64_encode('0'),
        'slug' => 'delete-selected',
    ]);

    $this->post($url, ['ids' => [1]])->assertForbidden();

    expect(Product::count())->toBe(2);
});

it('requires an ids array', function () {
    $this->post(bulkActionUrl(EndpointProductsTable::class), ['ids' => []])
        ->assertSessionHasErrors('ids');

    expect(Product::count())->toBe(2);
});

it('refuses a table the user is not authorized for', function () {
    $this->withoutExceptionHandling()
        ->post(bulkActionUrl(ForbiddenProductsTable::class), ['ids' => [1]]);
})->throws(UnauthorizedException::class);

it('leaves the rows untouched for an unauthorized table', function () {
    $this->post(bulkActionUrl(ForbiddenProductsTable::class), ['ids' => [1]]);

    expect(Product::count())->toBe(2);
});
