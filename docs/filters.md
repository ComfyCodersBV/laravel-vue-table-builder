# Filters

Filters render above the table in a dropdown. A select filter renders as a dropdown, a text filter as
an input, a date filter as a date input and a date range filter as two. The active value is applied to the query automatically.

## Select Filter

A standard dropdown filter applied directly as a WHERE clause on the column matching `$key`:

```php
$table->selectFilter(
    key: 'status',
    options: [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
    ],
);
```

### Signature

```php
selectFilter(
    string $key,
    array $options,
    ?string $label = null,
    ?string $defaultValue = null,
    bool $noFilterOption = true,
    ?string $noFilterOptionLabel = null,
): self
```

### Parameters

| Parameter             | Type           | Default       | Description                                                        |
|-----------------------|----------------|---------------|--------------------------------------------------------------------|
| `key`                 | `string`       | -             | Column name to filter on; also the query param key (`filter[key]`) |
| `options`             | `array`        | -             | Associative array of `value => label`                              |
| `label`               | `string\|null` | auto from key | Label shown above the dropdown                                     |
| `defaultValue`        | `string\|null` | `null`        | Pre-selected value                                                 |
| `noFilterOption`      | `bool`         | `true`        | Show a "show all" option at the top                                |
| `noFilterOptionLabel` | `string\|null` | `'-'`         | Label for the "show all" option                                    |

## Text Filter

A free text input applied as a `LIKE '%value%'` clause on the column matching `$key`:

```php
$table->textFilter(key: 'name', label: 'Name');
```

### Signature

```php
textFilter(
    string $key,
    ?string $label = null,
    ?string $defaultValue = null,
): self
```

### Parameters

| Parameter      | Type           | Default       | Description                                                        |
|----------------|----------------|---------------|--------------------------------------------------------------------|
| `key`          | `string`       | -             | Column name to filter on; also the query param key (`filter[key]`) |
| `label`        | `string\|null` | auto from key | Label shown above the input                                        |
| `defaultValue` | `string\|null` | `null`        | Pre-filled value                                                   |

Typing is debounced by 350ms. The value is matched case insensitively and is never split into
separate terms, so `de la` matches `Vincent de la Cruz` as one phrase. Select and callback filters
keep matching exactly and case sensitively.

## Callback Filter

A filter where you control the WHERE logic via a callback. Useful for filters that don't map 1:1 to a column name:

```php
$table->callbackFilter(
    key: 'verified',
    options: [
        '1' => 'Verified',
        '0' => 'Unverified',
    ],
    callback: function ($query, string $value) {
        $query->where('email_verified_at', $value === '1' ? '!=' : '=', null);
    },
);
```

### Signature

```php
callbackFilter(
    string $key,
    array $options,
    callable $callback,
    ?string $label = null,
    ?string $defaultValue = null,
    bool $noFilterOption = true,
    ?string $noFilterOptionLabel = null,
): self
```

The `$callback` receives:

1. `$query` - the Eloquent query builder
2. `$value` - the selected option value

## Date Filter

A date input applied as `whereDate(column, value)`:

```php
$table->dateFilter('created_at', label: 'Created on');
```

## Date Range Filter

Two date inputs, from and to, both inclusive. Either end may stay empty:

```php
$table->dateRangeFilter('period', label: 'Starts between', column: 'starts_at');
```

### Signatures

```php
dateFilter(
    string $key,
    ?string $label = null,
    ?string $defaultValue = null,
    ?string $column = null,
): self

dateRangeFilter(
    string $key,
    ?string $label = null,
    ?string $defaultFrom = null,
    ?string $defaultTo = null,
    ?string $column = null,
): self
```

`column` defaults to `key`, so the query parameter can have a different name than the column.
Dates are `Y-m-d`; anything else is ignored. A range travels as one value, `from..to`:

```
?filter[period]=2026-10-01..2026-10-31
?filter[period]=2026-10-01..
?filter[period]=..2026-10-31
```

`whereDate` compares the date part as stored. For a datetime column stored in UTC with users in
another timezone, or for a period that has to overlap a start and an end column, use a
`callbackFilter` and parse the value yourself.

Date filters are applied to query tables only, like the other filters.

## Query Parameter

Active filters are stored as `filter[key]=value`:

```
?filter[status]=active&filter[verified]=1
```

For named tables: `?users_filter[status]=active`. A Table class gets its name from the class, so
`BookingTable` uses `?booking_filter[...]`.

### Custom Query Keys

Choose the parameter name of filters and search yourself:

```php
$table->filterQueryKey('filter');   // ?filter[status]=active
$table->filterQueryKey('f');        // ?f[status]=active
```

Or replace the prefix of every parameter (sort, filter, page, perPage and columns) at once:

```php
$table->queryPrefix('b');    // ?b_sort=name&b_filter[status]=active&b_page=2
$table->queryPrefix('');     // ?sort=name&filter[status]=active&page=2
```

`filterQueryKey` wins over `queryPrefix` for filters. The resolved keys are sent to the component
as `queryKeys`, so the Vue side always writes the parameters the server reads. Keep the keys
unique when one page shows several tables.

## Default Value

Pre-select a filter value on first load:

```php
$table->selectFilter('status', ['active' => 'Active', 'inactive' => 'Inactive'], defaultValue: 'active');
```

## Multiple Filters

Chain as many filters as needed:

```php
$table
    ->selectFilter('status', ['active' => 'Active', 'inactive' => 'Inactive'])
    ->selectFilter('role', Role::pluck('name', 'id')->toArray())
    ->callbackFilter('has_orders', ['1' => 'Has orders', '0' => 'No orders'], function ($query, $value) {
        $value === '1'
            ? $query->has('orders')
            : $query->doesntHave('orders');
    });
```

## Checking Filter State

```php
$table->hasFilters();         // true if any filters are defined
$table->hasFiltersEnabled();  // true if any filter has an active value
```
