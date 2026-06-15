# Columns

Columns define what data is displayed and how each column behaves.

## Basic Usage

```php
$table->column('name', 'Name');
```

## Signature

```php
column(
    ?string $key = null,
    ?string $label = null,
    ?bool $canBeHidden = null,
    bool $hidden = false,
    bool|Closure $sortable = false,
    bool|string $searchable = false,
    ?bool $highlight = null,
    array|string|null $classes = null,
    ?callable $as = null,
    string $alignment = 'left',
    bool $clickable = true,
): self
```

## Parameters

| Parameter     | Type                  | Default                                           | Description                                          |
|---------------|-----------------------|---------------------------------------------------|------------------------------------------------------|
| `key`         | `string\|null`        | auto from label                                   | Column key matching the data field name              |
| `label`       | `string\|null`        | auto from key                                     | Column header label                                  |
| `canBeHidden` | `bool\|null`          | global default (`true`)                           | Whether the user can toggle this column's visibility |
| `hidden`      | `bool`                | `false`                                           | Hidden by default                                    |
| `sortable`    | `bool\|Closure`       | `false`                                           | Enable sorting; pass a closure for custom sort logic |
| `searchable`  | `bool\|string`        | `false`                                           | Add a search input for this column                   |
| `highlight`   | `bool\|null`          | `true` for first column if global default enabled | Visually highlight the cell                          |
| `classes`     | `array\|string\|null` | `null`                                            | CSS classes for this column's cells                  |
| `as`          | `callable\|null`      | `null`                                            | Transform the cell value before display              |
| `alignment`   | `string`              | `'left'`                                          | Text alignment: `'left'`, `'center'`, `'right'`      |
| `clickable`   | `bool`                | `true`                                            | Whether clicking this cell follows the row link      |

## Auto-labeling

If `label` is omitted, it is generated from `key` using `Str::headline()`:

```php
->column('created_at') // label: "Created At"
->column('company.name') // label: "Company Name"
```

If `key` is omitted, it is generated from `label` using `Str::kebab()`:

```php
->column(label: 'Full Name') // key: "full-name"
```

## Nested Relationships

Use dot notation to access relationship data:

```php
->column('company.name', 'Company')
->column('company.address.city', 'City')
```

When used with `QueryBuilder`, relationships are eager-loaded automatically. Sorting on nested columns requires the
`kirschbaum-development/eloquent-power-joins` package.

## Transforming Values

Use `as` to transform a value before it reaches the frontend:

```php
->column('status', 'Status', as: fn($value) => ucfirst($value))
->column('price', 'Price', as: fn($value, $item) => '$' . number_format($value, 2))
```

The second argument to the closure is the full row item.

### Plain text vs. HTML output

By default, string values returned from an `as` callback are escaped as plain text. This prevents XSS when displaying
user-controlled data.

To render raw HTML, return an `HtmlString` instance:

```php
use Illuminate\Support\HtmlString;

->column('ticket', 'Ticket', as: fn($ticket) => $ticket
    ? new HtmlString('<a href="' . route('tickets.show', $ticket) . '">' . e($ticket->title) . '</a>')
    : '-'
)
```

> **Note:** Always escape user-controlled values inside `HtmlString` using `e()`. The `HtmlString` wrapper signals
> intent, it does not escape for you.

## Custom Sort Logic

Pass a closure to `sortable` to implement custom ordering:

```php
->column('full_name', 'Name', sortable: function ($query, string $direction) {
    $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
})
```

## Column Visibility

Columns with `canBeHidden: true` (the default) appear in a visibility dropdown. Users can show/hide them — the selection
is persisted in the URL via the `columns[]` query parameter.

Prevent a column from being hidden:

```php
->column('id', 'ID', canBeHidden: false)
```

Hide a column by default (user can reveal it):

```php
->column('notes', 'Notes', hidden: true)
```

## CSS Classes

Apply Tailwind or custom classes to a column's cells:

```php
->column('id', 'ID', classes: 'w-16 text-muted-foreground')
```

For global cell/header classes across all columns, use [`class()`](global-defaults.md):

```php
$table->class(cell: 'py-2', head: 'bg-muted');
```

## Alignment

```php
->column('amount', 'Amount', alignment: 'right')
->column('status', 'Status', alignment: 'center')
```

## Non-clickable Cells

By default, clicking a cell follows the row link. Disable this for action columns:

```php
->column('actions', 'Actions', clickable: false, canBeHidden: false)
```

## Searchable Shorthand

Setting `searchable: true` automatically registers a search input for that column:

```php
->column('name', 'Name', searchable: true)
// Equivalent to:
->column('name', 'Name')
->searchInput('name', 'Name')
```

## Global Column Defaults

Configure defaults in a service provider:

```php
// All columns hidden by default (user must opt-in)
TableBuilder::defaultColumnCanBeHidden(true);

// Highlight the first column in every table
TableBuilder::defaultHighlightFirstColumn(true);
```
