<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class SpatieProductsTable extends AbstractTable
{
    public function for()
    {
        return SpatieQueryBuilder::for(Product::class)
            ->allowedFilters(AllowedFilter::exact('sku'))
            ->allowedSorts('name', 'price');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->withoutGlobalSearch()
            ->column('name', sortable: true)
            ->column('price', sortable: true)
            ->exportable(filename: 'products')
            ->bulkAction(label: 'Delete', each: fn (Product $product) => $product->delete())
            ->paginate(1);
    }
}

function spatieBulkUrl(array $pageQuery = []): string
{
    return URL::signedRoute('table.bulk-action', array_merge($pageQuery, [
        'table' => base64_encode(SpatieProductsTable::class),
        'action' => base64_encode('0'),
        'slug' => 'delete',
    ]));
}

beforeEach(function () {
    $this->app->register(QueryBuilderServiceProvider::class);

    Product::create(['name' => 'Chair', 'sku' => 'A', 'price' => 30]);
    Product::create(['name' => 'Table', 'sku' => 'B', 'price' => 10]);
    Product::create(['name' => 'Bench', 'sku' => 'B', 'price' => 20]);
});

it('runs a bulk action over the rows matching the posted state of a spatie table', function () {
    $this->postJson(spatieBulkUrl(), [
        'ids' => ['*'],
        'state' => 'filter%5Bsku%5D=B&sort=-price',
    ])->assertOk();

    expect(Product::query()->pluck('name')->all())->toBe(['Chair']);
});

it('lets the posted state replace the filters of the signed page query of a spatie table', function () {
    $this->postJson(spatieBulkUrl(['filter' => ['sku' => 'B']]), [
        'ids' => ['*'],
        'state' => 'filter%5Bsku%5D=A',
    ])->assertOk();

    expect(Product::query()->orderBy('name')->pluck('name')->all())->toBe(['Bench', 'Table']);
});

it('exports the rows matching the state appended to the url of a spatie table', function () {
    $url = SignedTableUrl::make('table.export', SpatieProductsTable::class);

    $content = $this->get($url.'&'.http_build_query(['filter' => ['sku' => 'B'], 'sort' => '-price']))
        ->assertOk()
        ->streamedContent();

    expect(array_values(array_filter(explode("\n", substr($content, 3)))))->toBe([
        'Name,Price',
        'Bench,20',
        'Table,10',
    ]);
});

it('builds the filtered query of a spatie table from the given request', function () {
    $table = new SpatieProductsTable;

    $names = $table->filteredQuery(Request::create('/', 'GET', ['filter' => ['sku' => 'B'], 'sort' => 'name']))
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Bench', 'Table'])
        ->and($table->filteredQuery()->count())->toBe(3);
});

it('does not narrow explicitly posted ids of a spatie table by the filters of the signed page query', function () {
    $chair = Product::query()
        ->where('name', 'Chair')
        ->firstOrFail();

    $this->postJson(spatieBulkUrl(['filter' => ['sku' => 'B']]), [
        'ids' => [$chair->id],
    ])->assertOk();

    expect(Product::query()->orderBy('name')->pluck('name')->all())->toBe(['Bench', 'Table']);
});
