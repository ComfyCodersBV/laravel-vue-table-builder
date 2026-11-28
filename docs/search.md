# Search

The package supports both per-column search inputs and a single global search bar.

## Global Search

Add a search bar that searches across multiple columns at once:

```php
$table->withGlobalSearch(columns: ['name', 'email']);
```

Always pass `columns`. Without them the search input falls back to its own key and looks for a column literally named
`global`:

```php
$table->withGlobalSearch(); // searches a column named "global"
```

The global search stores its value in `filter[global]`.

### Signature

```php
withGlobalSearch(?string $label = null, array $columns = [], ?callable $normalize = null): self
```

| Parameter   | Description                                                          |
|-------------|----------------------------------------------------------------------|
| `label`     | Placeholder text (defaults to translated "Search...")                |
| `columns`   | Columns to search; if empty, falls back to the input's key           |
| `normalize` | Transforms the typed term before it is applied, see [Normalizing the Search Term](#normalizing-the-search-term) |

### Remove Global Search

```php
$table->withoutGlobalSearch();
```

## Per-Column Search Inputs

Add a dedicated search input for a specific column or set of columns:

```php
$table->searchInput('name', 'Search by name');
```

Or search across multiple columns with one input:

```php
$table->searchInput(['first_name', 'last_name'], 'Search by name');
```

### Signature

```php
searchInput(
    array|string $key,
    ?string $label = null,
    ?string $defaultValue = null,
    array $columns = [],
    ?callable $normalize = null,
): self
```

| Parameter      | Type            | Default       | Description                                                                  |
|----------------|-----------------|---------------|------------------------------------------------------------------------------|
| `key`          | `string\|array` | -             | Query param key, or array of column names                                    |
| `label`        | `string\|null`  | auto from key | Label shown on the input                                                     |
| `defaultValue` | `string\|null`  | `null`        | Pre-filled search term                                                       |
| `columns`      | `array`         | `[]`          | Override which columns are searched (with optional search method per column) |
| `normalize`    | `callable\|null` | `null`       | Transforms the typed term before it is applied, see [Normalizing the Search Term](#normalizing-the-search-term) |

### Searchable Shorthand

`searchable: true` on a column registers a search input for that column, with the column key and label:

```php
->column('name', 'Name', searchable: true)
```

That is the same as:

```php
->column('name', 'Name')
->searchInput('name', 'Name')
```

## Normalizing the Search Term

A search input takes a `normalize` callable that transforms the typed term before it is applied, so a phone number
typed as `06 1234 5678` finds `0612345678`. It runs on the whole term, before the term is split into words, and also for
a collection resource. A term that normalizes to an empty string skips that search input, but still counts as an
active search filter. The search field keeps showing the value as typed.

```php
$table->column('phone', 'Phone', searchable: true, normalizeSearch: fn (string $term) => str_replace(' ', '', $term));
$table->withGlobalSearch(columns: ['phone', 'name'], normalize: fn (string $term) => trim($term));
$table->searchInput(key: 'phone', normalize: fn (string $term) => preg_replace('/\D/', '', $term));
```

`normalizeSearch` on `column()` only works together with `searchable: true`. A global search bar has one normalizer for
all its columns: when columns need different normalizers, register a separate `searchInput()` per column.

It needs `tranquil-tools/laravel-table-builder` 1.8.

## Search Methods

Each column in a search input can use a different matching strategy. Pass the method as the array value:

```php
use TranquilTools\TableBuilder\Components\SearchInput;

$table->searchInput(
    key: 'name',
    columns: [
        'name'  => SearchInput::WILDCARD,       // %term% (default)
        'email' => SearchInput::WILDCARD_RIGHT,  // term%
        'code'  => SearchInput::EXACT,           // = term
    ],
);
```

| Constant                      | SQL           | Description              |
|-------------------------------|---------------|--------------------------|
| `SearchInput::WILDCARD`       | `LIKE %term%` | Match anywhere (default) |
| `SearchInput::WILDCARD_LEFT`  | `LIKE %term`  | Match at end             |
| `SearchInput::WILDCARD_RIGHT` | `LIKE term%`  | Match at start           |
| `SearchInput::EXACT`          | `= term`      | Exact match              |

## Case Sensitivity

By default, searches are case-insensitive:

- MySQL: uses `LIKE` (case-insensitive by default collation)
- PostgreSQL: uses `ILIKE`

To enable case-sensitive search:

```php
TableBuilder::for(User::query())
    ->ignoreCase(false)
    ->column('code', 'Code')
    ->searchInput('code');
```

## Term Parsing

By default, search terms are split on spaces, and a row matches when **any** term matches **any** of the input's
columns. So `john doe` returns rows matching `john` OR `doe`. Quoted phrases are treated as a single term.

Disable this to treat the entire input as one search term:

```php
TableBuilder::for(User::query())
    ->parseTerms(false)
    ->column('name', 'Name')
    ->searchInput('name');
```

## Searching Relationships

Use dot notation to search inside a relationship:

```php
$table->searchInput(
    key: 'company_search',
    label: 'Company',
    columns: ['company.name' => SearchInput::WILDCARD],
);
```

The package generates a `whereHas` clause automatically.

## Query Parameters

Search values are stored as `filter[key]=term`:

```
?filter[global]=john&filter[name]=doe
```

## Checking Search State

```php
$table->hasSearchFiltersEnabled(); // true if any search input has a value
```

## Collection Resources

On a `Collection` resource the search runs in memory: the raw search term is lowercased and matched as a substring
against each configured column, without term splitting or search methods.

A `QueryBuilder` resource never passes through this filter: its rows are already filtered by the SQL query, paginated or
not, so term splitting and search methods keep working.

## Placement

By default the global search sits inline, right of the filter button and left of the pagination and toolbar buttons.
`searchPlacement('above')` puts it on its own full width row, with the filter button, pagination and toolbar buttons
on the row below:

```php
$table->withGlobalSearch()->searchPlacement('above');
```

Set it once for every table in a service provider; a table's own `searchPlacement()` still wins:

```php
TableBuilder::defaultSearchPlacement('above');
```

The placement only applies when the table has a global search. It needs `tranquil-tools/laravel-table-builder` 1.7.

## Global Default

Enable global search on every table in a service provider:

```php
TableBuilder::defaultGlobalSearch('Search...');

// Disable:
TableBuilder::defaultGlobalSearch(false);
```
