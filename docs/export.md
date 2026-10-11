# Export

Let users download the rows they are looking at as a CSV file. Export is opt-in per table:

```php
public function configure(TableBuilder $table): void
{
    $table
        ->column('name', 'Name')
        ->column('amount', 'Amount', as: fn (int $cents) => Money::format($cents))
        ->column('actions', export: false)
        ->exportable(filename: 'invoices')
        ->paginate(25);
}
```

The component shows an "Export" button next to the column selector. The file holds every row that
matches the current search and filters, in the current sort order, across all pages.

## Signature

```php
exportable(
    ?string $label = null,
    string|Closure|null $filename = null,
    ?array $columns = null,
    ?string $delimiter = null,
    ?string $decimalSeparator = null,
    ?string $url = null,
): self
```

| Parameter          | Description                                                                                           |
|--------------------|-------------------------------------------------------------------------------------------------------|
| `label`            | Button text. Defaults to the translated "Export"                                                      |
| `filename`         | File name, or a callback receiving the request. `.csv` is added. Defaults to the table name and date  |
| `columns`          | The columns to export, see [Columns](#columns). Defaults to every column without `export: false`      |
| `delimiter`        | Field separator. Defaults to the config, then to `;` for locales with a decimal comma and `,` otherwise |
| `decimalSeparator` | Decimal separator for numbers. Defaults to the config, then to the separator of the app locale        |
| `url`              | A route of your own to download from instead of the package route                                     |

## The File

- UTF-8 with a byte order mark, so Excel opens accents correctly.
- Streamed: the rows are read in chunks of `export.chunk_size` (1000) with `lazy()`, so a large
  table does not have to fit in memory.
- Safe to open in a spreadsheet: a text cell starting with `=`, `+`, `-`, `@`, a tab or a carriage
  return gets a leading `'`, so it is shown as text and never run as a formula. Numbers such as
  `-12` are left alone.
- Numbers use the decimal separator; booleans become "Yes" and "No" (translated); dates become
  `Y-m-d`, or `Y-m-d H:i:s` when they have a time; enums become their value.

The separators follow the app locale: `nl` writes `1250,75` in `;`-separated columns, `en` writes
`1250.75` in `,`-separated columns. Fix them for every table in the config:

```php
// config/vue-table-builder.php
'export' => [
    'delimiter' => ';',
    'decimal_separator' => ',',
    'chunk_size' => 1000,
],
```

## Columns

A cell holds the plain value of the column. A column with an `as:` callback exports what that
callback returns, with the markup of an `HtmlString` stripped and the `label` of an array taken.
Give a column an `exportAs:` callback when the file needs something else than the screen, and leave
columns such as buttons out with `export: false`:

```php
$table
    ->column('status', 'Status', as: fn ($status) => new HtmlString("<span class=\"badge\">{$status->label()}</span>"))
    ->column('amount', 'Amount', as: fn (int $cents) => Money::format($cents), exportAs: fn (int $cents) => $cents / 100)
    ->column('actions', export: false);
```

`exportAs` receives the value and the row, like `as`. Hidden columns are exported too.

Choose and order the exported columns with `columns`. A key that is not a column of the table is
read from the row, so the file can carry fields the screen does not show; a `key => label` pair
sets the heading:

```php
$table->exportable(columns: [
    'name',
    'email' => 'E-mail address',
    'company.name' => 'Company',
]);
```

## Authorization

The package route rebuilds the Table class from a signed url and calls `authorizeExport()`, which
falls back to `authorize()`:

```php
public function authorizeExport(Request $request): bool
{
    return $request->user()->can('export', Invoice::class);
}
```

Only offer the button to users who may use it; the route answers `404` for a table that is not
exportable:

```php
$table->when($this->user->can('export', Invoice::class), fn (TableBuilder $table) => $table->exportable());
```

The signed url names the table and its [signed parameters](security.md#tables-with-constructor-arguments).
The component appends the current sort, search and filters when the button is pressed; those are not
part of the signature and only narrow down the rows of that same table. Run the package routes in
your `web` stack with `auth` so the hook sees the user, see [Security](security.md).

## Custom Exports

Export from a controller of your own with the table class:

```php
Route::get('invoices/export', function (Request $request) {
    Gate::authorize('export', Invoice::class);

    return (new InvoiceTable($request->user()))->export($request);
})->name('invoices.export');
```

```php
$table->exportable(url: route('invoices.export'));
```

The component appends the current sort, search and filters to that url as well.

For another format (Excel, PDF, a queued job), ask the table for its query. `filteredQuery()` returns
the table's query with the search, filters and sort of the request applied and without pagination:

```php
$query = (new InvoiceTable($request->user()))->filteredQuery($request);

return Excel::download(new InvoicesExport($query), 'invoices.xlsx');
```

It is also available on a `TableBuilder` built from a query, and the table keeps its own rows: the
query is a fresh clone every time.

## Frontend

The button is a plain link with a `download` attribute, so the browser downloads the file and the
page stays where it is. The component exposes `exportUrl()`, which returns the signed url with the
current state appended, for a button of your own:

```vue
<script setup lang="ts">
import {useTemplateRef} from 'vue'

const invoices = useTemplateRef('invoices')
</script>

<template>
    <TableBuilder ref="invoices" :table="table"/>
    <a :href="invoices?.exportUrl() ?? '#'">Download CSV</a>
</template>
```

The table payload carries the button as `export`:

```ts
interface ExportConfig {
    label: string
    url: string | null
}
```
