<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Category;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class ExportProductsTable extends AbstractTable
{
    public static bool $mayExport = true;

    public function for()
    {
        return Product::query()->with('category');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->withGlobalSearch(columns: ['name'])
            ->selectFilter('is_active', ['1' => 'Active', '0' => 'Inactive'])
            ->column('name', 'Name', sortable: true)
            ->column('price', 'Price', sortable: true, as: fn (int $cents) => new HtmlString('<b>'.number_format($cents / 100, 2).'</b>'))
            ->column('category.name', 'Category')
            ->column('is_active', 'Active', boolean: true)
            ->column('actions', 'Actions', export: false)
            ->exportable(filename: 'products')
            ->paginate(1);
    }

    public function authorizeExport(Request $request): bool
    {
        return static::$mayExport;
    }
}

class SelectedColumnsExportTable extends ExportProductsTable
{
    public function configure(TableBuilder $table): void
    {
        parent::configure($table);

        $table
            ->column('sku', 'SKU', exportAs: fn (string $sku) => "SKU {$sku}")
            ->exportable(columns: [
                'sku',
                'name' => 'Product',
                'category_id' => 'Category id',
            ], delimiter: ';', decimalSeparator: ',');
    }
}

class NotExportableProductsTable extends AbstractTable
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

function exportCsv(string $url): array
{
    $content = test()->get($url)->assertOk()->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF");

    return array_map(
        fn (string $line) => str_getcsv($line, str_contains($line, ';') ? ';' : ',', '"', ''),
        array_values(array_filter(explode("\n", substr($content, 3)))),
    );
}

beforeEach(function () {
    ExportProductsTable::$mayExport = true;

    $category = Category::create(['name' => 'Furniture']);

    Product::create(['name' => 'Chair', 'sku' => 'C1', 'price' => 1250, 'category_id' => $category->id]);
    Product::create(['name' => 'Lamp', 'sku' => 'L1', 'price' => 990, 'is_active' => false]);
    Product::create(['name' => '=Table', 'sku' => 'T1', 'price' => 4000, 'category_id' => $category->id]);
});

it('sends a signed export url with the table payload', function () {
    $export = ExportProductsTable::build()->toArray()['export'];

    expect($export['label'])->toBe('Export')
        ->and($export['url'])->toContain('/table/export?')
        ->and($export['url'])->toContain('signature=')
        ->and($export['url'])->toContain('expires=');
});

it('streams every row of the query, not only the current page', function () {
    $response = $this->get(ExportProductsTable::build()->toArray()['export']['url']);

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('products.csv');

    expect(exportCsv(ExportProductsTable::build()->toArray()['export']['url']))->toBe([
        ['Name', 'Price', 'Category', 'Active'],
        ['Chair', '12.50', 'Furniture', 'Yes'],
        ['Lamp', '9.90', '', 'No'],
        ["'=Table", '40.00', 'Furniture', 'Yes'],
    ]);
});

it('applies the search, filters and sort appended to the url', function () {
    $url = ExportProductsTable::build()->toArray()['export']['url'];

    $rows = exportCsv($url.'&'.http_build_query([
        'export_products_filter' => ['is_active' => '1'],
        'export_products_sort' => '-price',
    ]));

    expect(array_column($rows, 0))->toBe(['Name', "'=Table", 'Chair']);

    $searched = exportCsv($url.'&'.http_build_query(['export_products_filter' => ['global' => 'lam']]));

    expect(array_column($searched, 0))->toBe(['Name', 'Lamp']);
});

it('exports the selected columns with their own labels, separators and values', function () {
    $rows = exportCsv(SelectedColumnsExportTable::build()->toArray()['export']['url']);

    expect($rows[0])->toBe(['SKU', 'Product', 'Category id'])
        ->and($rows[1])->toBe(['SKU C1', 'Chair', '1']);
});

it('refuses an export the table does not authorize', function () {
    ExportProductsTable::$mayExport = false;

    $this->get(SignedTableUrl::make('table.export', ExportProductsTable::class))->assertForbidden();
});

it('answers 404 for a table that is not exportable', function () {
    $this->get(SignedTableUrl::make('table.export', NotExportableProductsTable::class))->assertNotFound();
});

it('rejects an unsigned or tampered export url', function () {
    $url = SignedTableUrl::make('table.export', ExportProductsTable::class);

    $this->get('/table/export?table='.base64_encode(ExportProductsTable::class))->assertForbidden();

    $this->get(str_replace(
        urlencode(base64_encode(ExportProductsTable::class)),
        urlencode(base64_encode(SelectedColumnsExportTable::class)),
        $url,
    ))->assertForbidden();

    $this->get($url.'&params='.urlencode(SignedTableUrl::encodeParameters(['category' => 1])))->assertForbidden();
});

it('rejects an expired export url', function () {
    $url = SignedTableUrl::make('table.export', ExportProductsTable::class);

    $this->travel(13)->hours();

    $this->get($url)->assertForbidden();
});

it('exports from a route of your own through the table class', function () {
    $response = (new ExportProductsTable)->export(Request::create('/?export_products_filter[is_active]=0'));

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('Lamp')->not->toContain('Chair');
});

it('sends no package url for a table without a table class', function () {
    $table = TableBuilder::for(Product::query())->column('name')->exportable();

    expect($table->toArray()['export'])->toBe(['label' => 'Export', 'url' => null])
        ->and(TableBuilder::for(Product::query())->column('name')->exportable(url: '/products.csv')->toArray()['export']['url'])
        ->toBe('/products.csv')
        ->and(TableBuilder::for(Product::query())->column('name')->toArray()['export'])->toBeNull();
});
