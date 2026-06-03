# Global Defaults

Static methods on `TableBuilder` let you configure defaults once in a service provider so every table inherits them.

## Where to Set

Register defaults in `AppServiceProvider::boot()` or a dedicated service provider:

```php
use TranquilTools\TableBuilder\TableBuilder;

public function boot(): void
{
    TableBuilder::defaultPerPageOptions([10, 25, 50, 100]);
    TableBuilder::defaultPerPage(25);
    TableBuilder::defaultSearchDebounce(350);
    TableBuilder::defaultResetButton(true);
    TableBuilder::defaultPaginationScroll('top');
    TableBuilder::hidePaginationWhenResourceContainsOnePage(false);
    TableBuilder::defaultColumnCanBeHidden(true);
    TableBuilder::defaultHighlightFirstColumn(false);
    TableBuilder::defaultGlobalSearch(false);
}
```

## Reference

### Pagination

| Method                                                          | Default             | Description                                               |
|-----------------------------------------------------------------|---------------------|-----------------------------------------------------------|
| `TableBuilder::defaultPerPageOptions(array)`                    | `[10, 25, 50, 100]` | Available per-page options for every table                |
| `TableBuilder::defaultPerPage(int)`                             | `25`                | Default items per page when no `?perPage` query param is set |
| `TableBuilder::hidePaginationWhenResourceContainsOnePage(bool)` | `false`             | Hide pagination controls when all results fit on one page |
| `TableBuilder::defaultPaginationScroll(string)`                 | `'top'`             | Scroll to `'top'` on page change, or `''` for no scroll   |

### Search

| Method                                             | Default | Description                                                                 |
|----------------------------------------------------|---------|-----------------------------------------------------------------------------|
| `TableBuilder::defaultSearchDebounce(int $ms)`     | `350`   | Milliseconds to debounce search input changes                               |
| `TableBuilder::defaultGlobalSearch(string\|false)` | `false` | Enable global search on all tables with an optional label; `false` disables |

### Columns

| Method                                            | Default | Description                                       |
|---------------------------------------------------|---------|---------------------------------------------------|
| `TableBuilder::defaultColumnCanBeHidden(bool)`    | `true`  | Whether columns are toggleable by default         |
| `TableBuilder::defaultHighlightFirstColumn(bool)` | `false` | Visually highlight the first column on all tables |

### UI

| Method                                   | Default | Description                                               |
|------------------------------------------|---------|-----------------------------------------------------------|
| `TableBuilder::defaultResetButton(bool)` | `true`  | Show a reset button when any filter/search/sort is active |

## Per-Table Overrides

Any default can be overridden on an individual table:

```php
public function configure(TableBuilder $table): void
{
    $table
        ->perPageOptions([5, 10, 25])   // override per-page options
        ->withoutGlobalSearch()          // remove global search for this table
        ->name('orders');
}
```

## Reading Defaults

```php
TableBuilder::getDefaultPerPage();          // int
TableBuilder::getDefaultSearchDebounce();   // int
TableBuilder::getDefaultResetButton();      // bool
TableBuilder::getDefaultPaginationScroll(); // string
TableBuilder::hidesPaginationWhenResourceContainsOnePage(); // bool
```
