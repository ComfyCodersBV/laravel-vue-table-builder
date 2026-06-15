# Sorting

Columns can be made sortable so users can click column headers to sort the table.

## Enable Sorting

```php
->column('name', 'Name', sortable: true)
->column('created_at', 'Created', sortable: true)
```

Sortable columns show a sort indicator in the header. Clicking cycles through: ascending → descending → no sort.

## Query Parameter

The active sort is stored in the `sort` query parameter:

- `?sort=name` - ascending by `name`
- `?sort=-name` - descending by `name`
- (no param) - no sort applied

For named tables, the param is prefixed: `?users_sort=name`.

## Default Sort

Set the sort applied when no `sort` query param is present:

```php
$table->defaultSort('name');           // ascending
$table->defaultSort('name', 'desc');   // descending
$table->defaultSortDesc('created_at'); // shorthand for desc
```

You can also use a leading `-` in the sort key:

```php
$table->defaultSort('-created_at'); // descending
```

## Custom Sort Logic

Pass a closure to `sortable` for full control over the ORDER BY:

```php
->column('full_name', 'Name', sortable: function ($query, string $direction) {
    $query->orderBy('last_name', $direction)
          ->orderBy('first_name', $direction);
})
```

The closure receives:

1. `$query` - the Eloquent query builder
2. `$direction` - `'asc'` or `'desc'`

## Sorting on Relationship Columns

Sorting by a nested column (dot notation) requires the Power Joins package:

```bash
composer require kirschbaum-development/eloquent-power-joins
```

```php
->column('company.name', 'Company', sortable: true)
```

Without the package, use a custom sort closure instead:

```php
->column('company', 'Company', sortable: function ($query, string $direction) {
    $query->join('companies', 'users.company_id', '=', 'companies.id')
          ->orderBy('companies.name', $direction);
})
```

## Checking Sort State

In PHP you can check if any sort is active:

```php
$table->isSorted(); // bool
```
