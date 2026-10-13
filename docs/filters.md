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
    ?string $timezone = null,
): self

dateRangeFilter(
    string $key,
    ?string $label = null,
    ?string $defaultFrom = null,
    ?string $defaultTo = null,
    ?string $column = null,
    ?string $endColumn = null,
    ?string $timezone = null,
    array $presets = [],
    ?string $defaultPreset = null,
): self
```

`column` defaults to `key`, so the query parameter can have a different name than the column.
Dates are `Y-m-d`; anything else is ignored. A range travels as one value, `from..to`:

```
?filter[period]=2026-10-01..2026-10-31
?filter[period]=2026-10-01..
?filter[period]=..2026-10-31
```

Without `timezone`, `endColumn` or presets the filter compares the date part as stored with
`whereDate`, from and to inclusive.

### Display Timezone

A datetime column is usually stored in the application timezone (UTC), while users pick dates in
their own. With `timezone` the picked dates are days in that timezone: the range runs from the start
of the first day up to (not including) the start of the day after the last one, converted to
`config('app.timezone')` before it is compared.

```php
$table->dateRangeFilter('period', column: 'starts_at', timezone: 'Europe/Amsterdam');
```

On 11 October a booking stored as `2026-10-10 22:30:00` UTC (00:30 in Amsterdam) now counts as the
11th. Set it once for every date filter in the config:

```php
// config/table-builder.php
'display_timezone' => 'Europe/Amsterdam',
```

### Overlapping Periods

Records with a start and an end, such as bookings, match when their period overlaps the range. Pass
the end column as `endColumn`:

```php
$table->dateRangeFilter('period', column: 'starts_at', endColumn: 'ends_at');
```

A record matches when it starts before the end of the range and ends after its start. The end is
exclusive, so a booking that ends at midnight on the first day of the range does not match, and a
`null` end counts as open-ended. Either end of the range may stay empty: `2026-10-06..` finds
everything that has not ended before the 6th.

### Presets

Offer common periods in a dropdown next to the custom range:

```php
use TranquilTools\TableBuilder\Components\DateRangePreset;

$table->dateRangeFilter(
    'period',
    column: 'starts_at',
    endColumn: 'ends_at',
    presets: [
        'upcoming',
        'this_week',
        'this_month',
        'past' => 'Earlier',
        'last_30_days' => fn (CarbonImmutable $now) => [$now->subDays(30)->startOfDay(), $now],
        DateRangePreset::make('season', 'This season', fn (CarbonImmutable $now) => [$now->startOfYear()->addMonths(3), $now->startOfYear()->addMonths(9)]),
    ],
    defaultPreset: 'upcoming',
);
```

| Built-in key   | Range                                   |
|----------------|-----------------------------------------|
| `today`        | today                                   |
| `upcoming`     | from now on                             |
| `past`         | until now                               |
| `this_week`    | this week, from Carbon's `startOfWeek()`  |
| `last_week`    | the week before                         |
| `next_week`    | the week after                          |
| `this_month`   | this calendar month                     |
| `last_month`   | the month before                        |
| `next_month`   | the month after                         |
| `this_year`    | this calendar year                      |

A list entry is a built-in key with its translated label, a `key => label` pair renames a built-in, a
`key => callback` pair or a `DateRangePreset` adds your own. A callback receives the current time in
the display timezone and returns `[$from, $until]`: Carbon instances where `$until` is exclusive and
`null` leaves that end open.

The selected preset travels as its key, `?filter[period]=this_week`, so it is evaluated when the page
loads and a bookmarked link keeps meaning "this week". `defaultPreset` applies a preset when the
query has no value. Choosing "Any time" in the dropdown sends an empty range, `..`, which clears the
filter without bringing the default back. "Custom range" shows the two date inputs.

`upcoming` and `past` compare with the current time; with an end column, `upcoming` keeps records
that are still running and `past` keeps records that have started.

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
