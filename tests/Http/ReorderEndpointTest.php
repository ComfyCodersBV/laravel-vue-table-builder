<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\UnauthorizedException;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class EndpointReorderTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name')->reorderable('sort_order');
    }
}

class ForbiddenReorderTable extends EndpointReorderTable
{
    public function authorize(Request $request): bool
    {
        return false;
    }
}

function reorderUrl(string $tableClass): string
{
    return URL::signedRoute('table.reorder', [
        'table' => base64_encode($tableClass),
    ]);
}

beforeEach(function () {
    foreach (['A', 'B', 'C'] as $index => $name) {
        Product::create(['name' => $name, 'sku' => $name, 'sort_order' => $index + 1]);
    }
});

it('persists the new order through the signed endpoint', function () {
    $this->postJson(reorderUrl(EndpointReorderTable::class), ['id' => 3, 'to' => 1])
        ->assertOk()
        ->assertJson(['reordered' => true]);

    expect(Product::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['C', 'A', 'B']);
});

it('redirects back for an inertia request', function () {
    $this->from('/products')
        ->post(reorderUrl(EndpointReorderTable::class), ['id' => 1, 'to' => 2], ['X-Inertia' => 'true'])
        ->assertRedirect('/products');
});

it('rejects an unsigned request', function () {
    $this->postJson('/table/reorder?table='.base64_encode(EndpointReorderTable::class), ['id' => 3, 'to' => 1])
        ->assertForbidden();
});

it('rejects a table that does not authorize the request', function () {
    $this->withoutExceptionHandling();

    $this->postJson(reorderUrl(ForbiddenReorderTable::class), ['id' => 3, 'to' => 1]);
})->throws(UnauthorizedException::class);

it('answers 404 for a class that is not a table', function () {
    $this->postJson(reorderUrl(Product::class), ['id' => 3, 'to' => 1])
        ->assertNotFound();
});

it('answers a validation error for an unknown row', function () {
    $this->postJson(reorderUrl(EndpointReorderTable::class), ['id' => 99, 'to' => 1])
        ->assertJsonValidationErrors('id');
});

it('requires both ids', function () {
    $this->postJson(reorderUrl(EndpointReorderTable::class), [])
        ->assertJsonValidationErrors(['id', 'to']);
});
