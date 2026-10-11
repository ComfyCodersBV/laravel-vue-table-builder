# Totals

Add a footer row with a total per column. Totals are computed on the server, over every row that
matches the current search and filters, not only the rows on the current page:

```php
$table
    ->column('description')
    ->column('hours', 'Hours')
    ->column('amount', 'Amount', as: fn (int $cents) => Money::format($cents))
    ->total('hours')
    ->total('amount', format: fn (int $cents) => Money::format($cents))
    ->totalsLabel('Total')
    ->paginate(25);
```

## Signature

```php
total(
    string $key,
    string|Closure $using = 'sum',
    ?Closure $format = null,
    ?string $column = null,
    bool $pageOnly = false,
): self

totalsLabel(?string $label): self
```

| Parameter  | Description                                                                                       |
|------------|---------------------------------------------------------------------------------------------------|
| `key`      | The column the total is shown under                                                               |
| `using`    | `sum`, `count`, `avg`, `min`, `max`, or a callback that returns the value                         |
| `format`   | Turns the value into the text shown, for currencies, units and rounding                           |
| `column`   | The database column (or item key) to aggregate. Defaults to `key`                                 |
| `pageOnly` | Total only the rows on the current page instead of every matching row                            |

`totalsLabel()` shows a text such as "Total" in the first visible column, when that column has no
total of its own.

## How They Are Computed

On a query table each total is one aggregate query on the table's query with the active search and
filters applied and the ordering removed, so `->total('amount')` runs `select sum("amount")` over all
matching rows. A table built from a collection totals every row of the collection that matches the
search.

With `pageOnly: true` the total is computed from the rows on the current page.

## Callbacks

A callback receives the filtered query (a clone, free to change) and returns the value:

```php
$table->total('amount', using: fn (Builder $query) => $query->sum(DB::raw('quantity * unit_price')));

$table->total('hours', using: fn (Builder $query) => $query->where('billable', true)->sum('hours'));
```

With `pageOnly: true`, or on a collection table, it receives the rows as a `Collection` instead:

```php
$table->total('name', using: fn (Collection $rows) => $rows->count().' bookings', pageOnly: true);
```

## Formatting

`format` receives the computed value. A string it returns is escaped like a cell; return an
`Illuminate\Support\HtmlString` for markup. Without `format` the raw value is sent: a number, or
`null` when there are no rows to average.

```php
$table->total('amount', format: fn (?int $cents) => '€ '.number_format(($cents ?? 0) / 100, 2, ',', '.'));
```

## Frontend

The component renders the totals in a `<tfoot>` under the matching columns, and hides the footer
while the table is empty. A `total-{key}` slot renders a total yourself:

```vue
<TableBuilder :table="table">
    <template #total-hours="{value}">
        <strong>{{ value }} h</strong>
    </template>
</TableBuilder>
```

The slot also receives the `column` and the whole `totals` payload:

```ts
interface Totals {
    label: string | null
    values: Record<string, string | number | null>
}
```

With the [HTTP transport](http-transport.md) the totals come from the fetched response; the default
adapter reads a `totals` key in the same shape.
