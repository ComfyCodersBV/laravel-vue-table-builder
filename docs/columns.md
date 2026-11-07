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
    bool $boolean = false,
    ?callable $exportAs = null,
    bool $export = true,
    array|string|null $headerClasses = null,
    ?string $hideBelow = null,
): self
```

## Parameters

| Parameter     | Type                    | Default                          | Description                                                        |
|---------------|-------------------------|----------------------------------|--------------------------------------------------------------------|
| `key`         | `string\|null`          | derived from `label`             | Column key matching the data field name                            |
| `label`       | `string\|null`          | derived from `key`               | Column header label                                                |
| `canBeHidden` | `bool\|null`            | `defaultColumnCanBeHidden()`     | Whether the user can toggle this column's visibility               |
| `hidden`      | `bool`                  | `false`                          | Hidden by default                                                  |
| `sortable`    | `bool\|Closure`         | `false`                          | Enable sorting, or a closure that applies the ordering itself      |
| `searchable`  | `bool\|string`          | `false`                          | Register a search input for this column                            |
| `highlight`   | `bool\|null`            | `defaultHighlightFirstColumn()`  | Emphasize the column; the default only applies to the first column |
| `classes`     | `array\|string\|null`   | `null`                           | Per-column CSS classes                                             |
| `as`          | `callable\|null`        | `null`                           | Transform the cell value before display                            |
| `alignment`   | `string`                | `'left'`                         | Text alignment: `'left'`, `'center'`, `'right'`                    |
| `clickable`   | `bool`                  | `true`                           | Whether clicking this cell follows the row link (controls inside it never do) |
| `boolean`     | `bool`                  | `false`                          | Render a check or cross icon instead of the raw value              |
| `exportAs`    | `callable\|null`        | `null`                           | The value written to a CSV [export](export.md) instead of `as`     |
| `export`      | `bool`                  | `true`                           | Include the column in a CSV export                                 |
| `headerClasses` | `array\|string\|null` | `null`                           | CSS classes for the header cell only, see [CSS Classes](#css-classes) |
| `hideBelow`   | `string\|null`          | `null`                           | Hide the column below `'sm'`, `'md'`, `'lg'` or `'xl'`, see [Hiding a Column on Small Screens](#hiding-a-column-on-small-screens) |

Only `key` and `label` are safe to pass positionally; use named arguments for the rest.

## Redefining a Column

Registering the same key twice replaces the first definition instead of adding a second column:

```php
->column('name', 'Name')
->column('name', 'Full name') // one column, labeled "Full name"
```

## Auto-labeling

If `label` is omitted, it is generated from `key` using `Str::headline()`:

```php
->column('created_at') // label: "Created At"
->column('company.name') // label: "Company Name"
```

If `key` is omitted, it is derived from `label` using `Str::kebab()`:

```php
->column(label: 'First Name') // key: "first-name"
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

`QueryBuilder` accepts a closure for custom ordering. It receives the query and the direction of the active sort:

```php
->column('full_name', 'Name', sortable: function ($query, string $direction) {
    $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
})
```

## Column Visibility

Columns with `canBeHidden: true` (the default) appear in a visibility dropdown. The Vue component keeps that choice in
component state for the current page view; it is not written to the URL.

A `columns[]` query parameter is applied server-side: when it is present, every hideable column that is **not** listed
comes back as `hidden`. Use it to link to a table with a specific set of columns:

```
/products?columns[]=name&columns[]=sku
```

For a named table the parameter is namespaced, like every other table parameter: `?products_columns[]=name`. Columns
with `canBeHidden: false` are never hidden by it.

Prevent a column from being hidden:

```php
->column('id', 'ID', canBeHidden: false)
```

Hide a column by default (user can reveal it):

```php
->column('notes', 'Notes', hidden: true)
```

The "Columns" menu only renders when at least one column can be hidden. For a small or fixed table, turn it off
while leaving `canBeHidden` alone:

```php
$table->withoutColumnToggle();

$table->columnToggle(false);
```

The payload then carries `columnToggle: false`. A column that is `hidden: true` stays hidden, because nothing can reveal
it any more.

## CSS Classes

Per-column classes accept a string, a (nested) array of strings, or a conditional array:

```php
->column('id', 'ID', classes: 'w-16 text-muted-foreground')
->column('id', 'ID', classes: ['w-16', 'text-muted-foreground'])
->column('id', 'ID', classes: ['w-16' => true, 'hidden' => $compact])
```

The classes end up in the `class` key of the column payload and reach the body cells and the totals cell, not the
header cell. Give the header its own classes with `headerClasses`, which takes the same forms and arrives as
`head_class`:

```php
->column('amount', 'Amount', classes: 'text-right tabular-nums', headerClasses: 'w-32 text-right')
```

To hide a column on small screens, use `hideBelow` rather than responsive classes: it hides the header too.

For global cell/header classes across all columns, use [`class()`](global-defaults.md):

```php
$table->class(cell: 'py-2', head: 'bg-muted');
```

## Hiding a Column on Small Screens

`hideBelow` hides a less important column below a breakpoint: its header, its cells and its total, so the header stays
aligned with the cells. The breakpoints are Tailwind's: `sm`, `md`, `lg` and `xl`.

```php
->column('name', 'Name')
->column('email', 'Email', hideBelow: 'md')      // hidden on phones
->column('created_at', 'Created', hideBelow: 'lg') // hidden on phones and tablets
```

An unknown breakpoint throws an `InvalidArgumentException`. The payload carries it as `hide_below`, and the renderer
turns it into fixed classes such as `hidden md:table-cell`. Those classes live in the component's `.vue` files, so the
`@source` line from the [installation](installation.md#tailwind-css) covers them.

- The first visible column always shows, also with `hideBelow`: it holds the keyboard row link in the table and is
  the title of a tile.
- In the [grid layout](grid-layout.md) the column's `Label: value` line hides below the same breakpoint. Combined
  with `gridBelow('md')`, a column with `hideBelow: 'md'` or wider is left out of the phone tiles and shows in the
  table from `md`.
- The columns menu still lists the column, checked while it is hidden by the breakpoint. When the user turns it off
  and on again there, it shows at every width until the page reloads: an explicit choice wins over the breakpoint.
- `hidden: true` still hides a column at every width until the user turns it on.

## Alignment

```php
->column('amount', 'Amount', alignment: 'right')
->column('status', 'Status', alignment: 'center')
```

## Boolean Columns

```php
->column('is_active', 'Active', boolean: true)
```

The cell renders a green check for `true`, `1` or `'1'` and a muted cross for anything else. Every
other value type is still rendered as text, so use an `as:` closure when you need a label instead of
an icon.

## Non-clickable Cells

By default, clicking a cell follows the row link, except on a button, link, input or other control inside it. Disable
it for the whole cell, for example on action columns:

```php
->column('actions', 'Actions', clickable: false, canBeHidden: false)
```

## Searchable Shorthand

`searchable: true` registers a search input for the column, using the column key and label:

```php
->column('name', 'Name', searchable: true)
```

That is shorthand for:

```php
->column('name', 'Name')
->searchInput('name', 'Name')
```

Any non-empty string is treated the same as `true`. Use [`searchInput()`](search.md) directly when you need multiple
columns in one input or a specific search method.

## Global Column Defaults

```php
// Let the user toggle every column's visibility (default: true)
TableBuilder::defaultColumnCanBeHidden(true);

// Highlight the first column in every table
TableBuilder::defaultHighlightFirstColumn(true);
```

Both are read when a column is registered, so set them in a service provider before any table is built. Passing
`canBeHidden:` or `highlight:` explicitly on a column always wins.
