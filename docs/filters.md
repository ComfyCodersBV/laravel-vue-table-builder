# Filters

Filters render above the table in a dropdown. A select filter renders as a dropdown, a text filter as
an input. The active value is applied to the query automatically.

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

## Query Parameter

Active filters are stored as `filter[key]=value`:

```
?filter[status]=active&filter[verified]=1
```

For named tables: `?users_filter[status]=active`.

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
