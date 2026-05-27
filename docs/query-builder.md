# Query Builder

`QueryBuilder` extends `TableBuilder` and is returned automatically when you pass an Eloquent query builder or relation
to `TableBuilder::for()`. It handles all database operations: filtering, searching, sorting, and pagination.

## How It Is Created

```php
// These all return a QueryBuilder instance:
TableBuilder::for(User::query())
TableBuilder::for(User::class)
TableBuilder::for($user->orders())
TableBuilder::for(new User)
```

## Pagination Methods

Must call one of these to execute the query:

```php
->paginate(15)         // LengthAwarePaginator — shows total count
->simplePaginate(15)   // SimplePaginator — Previous/Next only
->cursorPaginate(15)   // CursorPaginator — efficient for huge tables
->noPagination()       // Fetch all results with get()
```

Omitting `perPage` uses the first value from `perPageOptions`.

## Case Sensitivity

```php
->ignoreCase(true)   // case-insensitive search (default)
->ignoreCase(false)  // case-sensitive search
```

- MySQL: toggles between `LIKE` and `LIKE BINARY`
- PostgreSQL: toggles between `ILIKE` and `LIKE`

## Term Parsing

By default, search terms are split on spaces: `"john doe"` searches for rows matching both `john` AND `doe`. Quoted
phrases in the input are kept together.

```php
->parseTerms(true)   // split on spaces (default)
->parseTerms(false)  // treat entire input as one term
```

## Eager Loading

Columns using dot notation for relationships are eager-loaded automatically:

```php
->column('company.name', 'Company')
// Adds: $query->with('company')
```

## Filter Application Order

When `loadResource()` is called:

1. Callback/select filters applied (`applyFilters`)
2. Search inputs applied (`applySearchInputs`)
3. Sorting + eager loading applied (`applySortingAndEagerLoading`)
4. Default sort applied if no sort query param
5. Pagination executed

## Spatie QueryBuilder Support

You can pass a [Spatie QueryBuilder](https://github.com/spatie/laravel-query-builder) instance. In that case the package
skips its own filter/search/sort application and lets Spatie handle those, but still manages pagination and the Vue
frontend:

```php
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

$query = SpatieQueryBuilder::for(User::class)
    ->allowedFilters(['name', 'email'])
    ->allowedSorts(['name', 'created_at']);

TableBuilder::for($query)->paginate(15);
```

## Bulk Action Processing

When a bulk action targets "all results", the query re-applies all active filters and search inputs before chunking:

```php
->bulkAction('Export', each: fn($user) => $user->export())
```

Rows are processed in chunks of 1,000 via `chunkById` to handle large datasets safely.

## Conditionable

`QueryBuilder` (like `TableBuilder`) uses Laravel's `Conditionable` trait, so you can use `when()` and `unless()`:

```php
TableBuilder::for(User::query())
    ->when($request->user()->isAdmin(), fn($table) => $table->column('secret_field', 'Secret'))
    ->column('name', 'Name')
    ->paginate(15);
```
