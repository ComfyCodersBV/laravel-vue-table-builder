<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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

class FilteredEndpointProductsTable extends EndpointProductsTable
{
    public function configure(TableBuilder $table): void
    {
        parent::configure($table);

        $table->selectFilter('sku', [
            'A' => 'A',
            'B' => 'B',
        ]);
    }
}

class ForbiddenProductsTable extends EndpointProductsTable
{
    public function authorize(Request $request): bool
    {
        return false;
    }
}

function bulkActionUrl(string $tableClass, array $pageQuery = []): string
{
    return URL::signedRoute('table.bulk-action', array_merge($pageQuery, [
        'table' => base64_encode($tableClass),
        'action' => base64_encode('0'),
        'slug' => 'delete-selected',
    ]));
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

it('applies the filters of the signed page query when all rows are selected', function () {
    $url = bulkActionUrl(FilteredEndpointProductsTable::class, [
        'filtered_endpoint_products_filter' => [
            'sku' => 'A',
        ],
    ]);

    $this->post($url, ['ids' => ['*']])->assertRedirect();

    expect(Product::pluck('name')->all())->toBe(['Table']);
});

it('applies the posted table state when all rows are selected', function () {
    $this->postJson(bulkActionUrl(FilteredEndpointProductsTable::class), [
        'ids' => ['*'],
        'state' => 'filtered_endpoint_products_filter%5Bsku%5D=B',
    ])->assertOk();

    expect(Product::pluck('name')->all())->toBe(['Chair']);
});

it('lets the posted table state replace the filters of the signed page query', function () {
    $url = bulkActionUrl(FilteredEndpointProductsTable::class, [
        'filtered_endpoint_products_filter' => [
            'sku' => 'A',
        ],
    ]);

    $this->postJson($url, [
        'ids' => ['*'],
        'state' => '',
    ])->assertOk();

    expect(Product::count())->toBe(0);
});

it('ignores the posted table state for explicitly selected ids', function () {
    $this->postJson(bulkActionUrl(FilteredEndpointProductsTable::class), [
        'ids' => [1],
        'state' => 'filtered_endpoint_products_filter%5Bsku%5D=B',
    ])->assertOk();

    expect(Product::pluck('name')->all())->toBe(['Table']);
});

it('takes only the sort and filters from the posted table state', function () {
    $this->postJson(bulkActionUrl(FilteredEndpointProductsTable::class), [
        'ids' => ['*'],
        'state' => 'filtered_endpoint_products_filter%5Bsku%5D=B&table=x&action=MQ%3D%3D',
    ])->assertOk();

    expect(Product::pluck('name')->all())->toBe(['Chair']);
});

it('answers a json request with json instead of a redirect to the page', function () {
    $this->postJson(bulkActionUrl(EndpointProductsTable::class), ['ids' => [1]])
        ->assertOk()
        ->assertExactJson([
            'performed' => true,
        ]);

    expect(Product::pluck('name')->all())->toBe(['Table']);
});

it('redirects an inertia request back to the page', function () {
    $this->postJson(bulkActionUrl(EndpointProductsTable::class), ['ids' => [1]], [
        'X-Inertia' => 'true',
    ])->assertRedirect();
});

it('answers a json request without ids with a validation error', function () {
    $this->postJson(bulkActionUrl(EndpointProductsTable::class), ['ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');

    expect(Product::count())->toBe(2);
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
})->throws(AuthorizationException::class);

it('answers 403 for a table the user is not authorized for', function () {
    $this->post(bulkActionUrl(ForbiddenProductsTable::class), ['ids' => [1]])
        ->assertForbidden();
});

it('leaves the rows untouched for an unauthorized table', function () {
    $this->post(bulkActionUrl(ForbiddenProductsTable::class), ['ids' => [1]]);

    expect(Product::count())->toBe(2);
});
