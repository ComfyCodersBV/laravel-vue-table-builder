# Pagination

The package supports three pagination strategies, all handled by Laravel's built-in paginators.

## Strategies

### Length-Aware Pagination (default)

Shows total results count and page links. Requires a `COUNT(*)` query.

```php
$table->paginate(15); // 15 per page
$table->paginate();   // uses default_per_page from config (25)
```

### Simple Pagination

No total count. Shows only Previous/Next links. Faster for large datasets.

```php
$table->simplePaginate(15);
```

### Cursor Pagination

Cursor-based navigation. Efficient for very large tables where offset pagination is slow.

```php
$table->cursorPaginate(15);
```

### No Pagination

Fetch all results without pagination:

```php
$table->noPagination();
```

## Per Page Options

A per-page size dropdown is shown automatically in the pagination footer. Configure the available options per table:

```php
$table->perPageOptions([10, 25, 50, 100]);
```

The current `perPage` value from the query string (`?perPage=25`) is applied automatically — values not in the options
list are ignored.

The default per-page size and available options can be set globally via the config file (
`config/vue-table-builder.php`):

```php
return [
    'default_per_page' => 25,
    'per_page_options' => [
        10,
        25,
        50,
        100,
    ],
];
```

Or in a service provider:

```php
TableBuilder::defaultPerPageOptions([10, 25, 50, 100]);
TableBuilder::defaultPerPage(25);
```

## Query Parameters

| Param     | Description                                               |
|-----------|-----------------------------------------------------------|
| `page`    | Current page number                                       |
| `perPage` | Items per page (must match one of the configured options) |

For named tables: `?users_page=2&users_perPage=30`.

## Hide Pagination on Single Page

If the dataset fits on a single page, the pagination controls can be hidden automatically:

```php
TableBuilder::hidePaginationWhenResourceContainsOnePage(true);
```

## Pagination Scroll Behavior

Control whether the page scrolls to the top after navigating to a new page:

```php
TableBuilder::defaultPaginationScroll('top');  // scroll to top (default)
TableBuilder::defaultPaginationScroll('');     // no scroll
```

## Helper Methods

These can be called after `loadResource()` on the PHP side if needed:

```php
$table->perPage();           // items per page (int)
$table->totalOnThisPage();   // count of items on current page
$table->totalOnAllPages();   // total result count (paginated) or total items
$table->page();              // current page number
$table->isEmpty();           // true if no results
$table->isNotEmpty();        // true if has results
```

## Collection Resources

When passing a `Collection` or plain array (not a query builder), call `paginate()` on the resource itself before
passing it to the table:

```php
$items = collect([...])->paginate(15); // Laravel macro or custom

$table = TableBuilder::for($items)
    ->column('name', 'Name');
```

Note: filtering and searching on collection resources uses in-memory PHP filtering, not database queries.
