# Columns

Columns define what data is displayed and how each column behaves.

## Basic Usage

```php
$table->column('name', 'Name');
```

## Signature

```php
column(
    string $key,
    string $label = '',
    bool $canBeHidden = true,
    bool $hidden = false,
    bool $sortable = false,
    bool $searchable = false,
    string $alignment = 'left',
    ?callable $as = null,
    bool $clickable = true,
): self
```

## Parameters

| Parameter     | Type             | Default         | Description                                          |
|---------------|------------------|-----------------|------------------------------------------------------|
| `key`         | `string`         | -               | Column key matching the data field name              |
| `label`       | `string`         | auto from key   | Column header label                                  |
| `canBeHidden` | `bool`           | `true`          | Whether the user can toggle this column's visibility |
| `hidden`      | `bool`           | `false`         | Hidden by default                                    |
| `sortable`    | `bool`           | `false`         | Enable sorting                                       |
| `searchable`  | `bool`           | `false`         | Accepted but currently ignored (see below)           |
| `alignment`   | `string`         | `'left'`        | Text alignment: `'left'`, `'center'`, `'right'`      |
| `as`          | `callable\|null` | `null`          | Transform the cell value before display              |
| `clickable`   | `bool`           | `true`          | Whether clicking this cell follows the row link      |

## Current Limitations

`TableBuilder` defines its own `column()` and `columns()` methods, which take precedence over the richer versions in the
`HasColumns` trait. Until that duplication is resolved, the following are **not** available through
`TableBuilder::column()`:

| Feature                                     | Behaviour                                                       |
|---------------------------------------------|-----------------------------------------------------------------|
| `classes:` and `highlight:`                 | Unknown named argument - passing them raises an error           |
| `sortable:` with a closure                  | `TypeError`: the parameter is typed `bool`                      |
| `searchable: true`                          | Accepted, but no search input is registered                     |
| `key` omitted (derived from `label`)        | `key` is required                                               |
| Repeating a key to redefine a column        | The column is added twice instead of replacing the first        |
| `?columns[]=` visibility in the query param | Not applied server-side; `hidden` only reflects the PHP config  |
| `defaultColumnCanBeHidden()`                | No effect                                                       |
| `defaultHighlightFirstColumn()`             | No effect                                                       |

Register a search input explicitly instead of using `searchable:`:

```php
->column('name', 'Name')
->searchInput('name', 'Name')
```

## Auto-labeling

If `label` is omitted, it is generated from `key` using `Str::headline()`:

```php
->column('created_at') // label: "Created At"
->column('company.name') // label: "Company Name"
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

Every cell is rendered with `v-html` on the frontend, so the backend guarantees the value is safe HTML.

String values are escaped as plain text, whether they come straight from the model or from an `as` callback. This
prevents XSS when displaying user-controlled data:

```php
->column('name') // a name of '<b>Bold</b>' renders as the literal text <b>Bold</b>
```

Only strings are escaped; integers, booleans, `null` and arrays are passed through untouched.

To render raw HTML, return an `HtmlString` instance from an `as` callback:

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

`QueryBuilder` supports a closure for custom ordering, but `TableBuilder::column()` types `sortable` as `bool`, so a
closure cannot be passed yet (see [Current Limitations](#current-limitations)). The intended usage is:

```php
->column('full_name', 'Name', sortable: function ($query, string $direction) {
    $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
})
```

## Column Visibility

Columns with `canBeHidden: true` (the default) appear in a visibility dropdown. Users can show/hide them - the selection
is persisted in the URL via the `columns[]` query parameter and applied by the Vue component.

Prevent a column from being hidden:

```php
->column('id', 'ID', canBeHidden: false)
```

Hide a column by default (user can reveal it):

```php
->column('notes', 'Notes', hidden: true)
```

## CSS Classes

Per-column classes are carried by the `Column` component, but `classes:` cannot be passed through
`TableBuilder::column()` yet (see [Current Limitations](#current-limitations)):

```php
->column('id', 'ID', classes: 'w-16 text-muted-foreground')
```

Note that a conditional array (`['w-16' => true]`) is flattened before it is turned into a class string, so only plain
strings and plain arrays of strings produce the expected result.

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

`searchable: true` is intended to register a search input for the column, but the argument is currently ignored (see
[Current Limitations](#current-limitations)). Register the input explicitly:

```php
->column('name', 'Name')
->searchInput('name', 'Name')
```

## Global Column Defaults

These defaults only apply to `HasColumns::column()`, which is currently shadowed by `TableBuilder::column()`, so they
have no effect yet:

```php
// All columns hidden by default (user must opt-in)
TableBuilder::defaultColumnCanBeHidden(true);

// Highlight the first column in every table
TableBuilder::defaultHighlightFirstColumn(true);
```
