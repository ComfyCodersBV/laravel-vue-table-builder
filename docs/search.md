# Search

The package supports both per-column search inputs and a single global search bar.

## Global Search

Add a search bar that searches across multiple columns at once:

```php
$table->withGlobalSearch(columns: ['name', 'email']);
```

Without specifying columns, the global search uses all columns marked as `searchable`:

```php
$table
    ->column('name', 'Name', searchable: true)
    ->column('email', 'Email', searchable: true)
    ->withGlobalSearch();
```

The global search stores its value in `filter[global]`.

### Signature

```php
withGlobalSearch(?string $label = null, array $columns = []): self
```

| Parameter | Description                                            |
|-----------|--------------------------------------------------------|
| `label`   | Placeholder text (defaults to translated "Search...")  |
| `columns` | Columns to search; if empty, uses `searchable` columns |

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
): self
```

| Parameter      | Type            | Default       | Description                                                                  |
|----------------|-----------------|---------------|------------------------------------------------------------------------------|
| `key`          | `string\|array` | -             | Query param key, or array of column names                                    |
| `label`        | `string\|null`  | auto from key | Label shown on the input                                                     |
| `defaultValue` | `string\|null`  | `null`        | Pre-filled search term                                                       |
| `columns`      | `array`         | `[]`          | Override which columns are searched (with optional search method per column) |

### Searchable Shorthand

```php
->column('name', 'Name', searchable: true)
// Equivalent to:
->column('name', 'Name')
->searchInput('name', 'Name')
```

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
    ->column('code', 'Code', searchable: true);
```

## Term Parsing

By default, search terms are split on spaces so that `"john doe"` searches for rows matching both `john` AND `doe`.
Quoted phrases are treated as a single term.

Disable this to treat the entire input as one search term:

```php
TableBuilder::for(User::query())
    ->parseTerms(false)
    ->column('name', 'Name', searchable: true);
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

## Global Default

Enable global search on every table in a service provider:

```php
TableBuilder::defaultGlobalSearch('Search...');

// Disable:
TableBuilder::defaultGlobalSearch(false);
```
