<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Category;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class CategoryProductsTable extends AbstractTable
{
    public static array $authorizedBulkActions = [];

    public function __construct(private readonly Category $category) {}

    public function for()
    {
        return Product::query()
            ->where('category_id', $this->category->getKey())
            ->orderBy('sort_order');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('name')
            ->reorderable('sort_order')
            ->bulkAction('Delete selected', each: fn (Product $product) => $product->delete())
            ->bulkAction('Deactivate', each: fn (Product $product) => $product->update(['is_active' => false]));
    }

    public function signedParameters(): array
    {
        return [
            'category' => $this->category->getKey(),
        ];
    }

    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return new static(Category::query()->findOrFail($parameters['category']));
    }

    public function authorizeBulkAction(Request $request, int $key): bool
    {
        static::$authorizedBulkActions[] = $key;

        return $key === 0;
    }
}

class UnorderableProductsTable extends AbstractTable
{
    public function for()
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name');
    }
}

function categoryTable(string $name): array
{
    $category = Category::create(['name' => $name]);

    foreach (['A', 'B', 'C'] as $index => $product) {
        Product::create([
            'category_id' => $category->id,
            'name' => "{$name} {$product}",
            'sku' => "{$name}{$product}",
            'sort_order' => $index + 1,
        ]);
    }

    return [$category, CategoryProductsTable::build($category)->toArray()];
}

beforeEach(function () {
    CategoryProductsTable::$authorizedBulkActions = [];
});

it('signs the reorder url with an expiry and the table parameters', function () {
    [$category, $payload] = categoryTable('Chairs');

    parse_str((string) parse_url($payload['reorder']['url'], PHP_URL_QUERY), $query);

    expect($query)->toHaveKeys(['table', 'params', 'expires', 'signature'])
        ->and(SignedTableUrl::decodeParameters($query['params']))->toBe(['category' => $category->id]);
});

it('reorders within the table bound to the signed parameters', function () {
    categoryTable('Chairs');
    [, $payload] = categoryTable('Tables');

    $this->postJson($payload['reorder']['url'], ['id' => 6, 'to' => 4])->assertOk();

    expect(Product::query()->where('category_id', 2)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Tables C', 'Tables A', 'Tables B'])
        ->and(Product::query()->where('category_id', 1)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Chairs A', 'Chairs B', 'Chairs C']);
});

it('rejects a row of another table bound to other parameters', function () {
    categoryTable('Chairs');
    [, $payload] = categoryTable('Tables');

    $this->postJson($payload['reorder']['url'], ['id' => 1, 'to' => 4])
        ->assertJsonValidationErrors('id');
});

it('rejects swapped table parameters', function () {
    [$chairs] = categoryTable('Chairs');
    [, $payload] = categoryTable('Tables');

    $url = str_replace(
        urlencode(SignedTableUrl::encodeParameters(['category' => 2])),
        urlencode(SignedTableUrl::encodeParameters(['category' => $chairs->id])),
        $payload['reorder']['url'],
    );

    $this->postJson($url, ['id' => 1, 'to' => 2])->assertForbidden();
});

it('rejects an expired url', function () {
    [, $payload] = categoryTable('Chairs');

    $this->travel(721)->minutes();

    $this->postJson($payload['reorder']['url'], ['id' => 3, 'to' => 1])->assertForbidden();
});

it('honours the configured signature lifetime', function () {
    config()->set('vue-table-builder.routes.signature_ttl', 5);

    [, $payload] = categoryTable('Chairs');

    $this->travel(6)->minutes();

    $this->postJson($payload['reorder']['url'], ['id' => 3, 'to' => 1])->assertForbidden();
});

it('signs urls without an expiry when the lifetime is disabled', function () {
    config()->set('vue-table-builder.routes.signature_ttl', null);

    [, $payload] = categoryTable('Chairs');

    expect($payload['reorder']['url'])->not->toContain('expires=');

    $this->travel(1)->years();

    $this->postJson($payload['reorder']['url'], ['id' => 3, 'to' => 1])->assertOk();
});

it('performs a bulk action only on rows of the bound table', function () {
    categoryTable('Chairs');
    [, $payload] = categoryTable('Tables');

    $this->post($payload['bulkActions'][0]->url, ['ids' => ['*']])->assertRedirect();

    expect(Product::query()->pluck('name')->all())->toBe(['Chairs A', 'Chairs B', 'Chairs C']);
});

it('asks the table to authorize the bulk action by its key', function () {
    [, $payload] = categoryTable('Chairs');

    $this->post($payload['bulkActions'][1]->url, ['ids' => [1]])->assertForbidden();

    expect(CategoryProductsTable::$authorizedBulkActions)->toBe([1])
        ->and(Product::query()->where('is_active', false)->count())->toBe(0);
});

it('answers 404 for a bulk action the table does not have', function () {
    Product::create(['name' => 'A', 'sku' => 'A']);

    $url = SignedTableUrl::make('table.bulk-action', UnorderableProductsTable::class, [], [
        'action' => base64_encode('0'),
        'slug' => 'missing',
    ]);

    $this->post($url, ['ids' => [1]])->assertNotFound();

    expect(Product::query()->count())->toBe(1);
});

it('answers 404 for a reorder on a table that is not reorderable', function () {
    Product::create(['name' => 'A', 'sku' => 'A']);
    Product::create(['name' => 'B', 'sku' => 'B']);

    $url = SignedTableUrl::make('table.reorder', UnorderableProductsTable::class);

    $this->postJson($url, ['id' => 1, 'to' => 2])->assertNotFound();
});

it('answers 404 for parameters that cannot be decoded', function () {
    $url = SignedTableUrl::make('table.reorder', UnorderableProductsTable::class, [], [
        'params' => 'not-base64-json!',
    ]);

    $this->postJson($url, ['id' => 1, 'to' => 2])->assertNotFound();
});

it('runs the package routes inside the configured middleware', function () {
    config()->set('vue-table-builder.routes.middleware', ['auth']);

    require __DIR__.'/../../routes/web.php';

    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('table.reorder')->gatherMiddleware())->toContain('auth');

    [, $payload] = categoryTable('Chairs');

    $this->postJson($payload['reorder']['url'], ['id' => 3, 'to' => 1])->assertUnauthorized();
});
