# Bulk Actions

Bulk actions let users select one or more rows and apply an action to all of them. Actions are processed server-side via
a signed POST route.

## Basic Usage

```php
$table->bulkAction(
    label: 'Delete',
    each: fn($user) => $user->delete(),
);
```

## Signature

```php
bulkAction(
    string $label,
    ?callable $each = null,
    ?callable $before = null,
    ?callable $after = null,
    bool|string $confirm = '',
    string $confirmText = '',
    string $confirmButton = '',
    string $cancelButton = '',
    bool|string $requirePassword = false,
): self
```

## Parameters

| Parameter         | Type             | Default | Description                                                         |
|-------------------|------------------|---------|---------------------------------------------------------------------|
| `label`           | `string`         | —       | Button label shown in the UI                                        |
| `each`            | `callable\|null` | `null`  | Called once per selected row; receives the model instance           |
| `before`          | `callable\|null` | `null`  | Called once before processing starts; receives the array of IDs     |
| `after`           | `callable\|null` | `null`  | Called once after all rows are processed; receives the array of IDs |
| `confirm`         | `bool\|string`   | `''`    | Show a confirmation dialog; pass `true` or a custom message string  |
| `confirmText`     | `string`         | `''`    | Body text inside the dialog                                         |
| `confirmButton`   | `string`         | `''`    | Confirm button label                                                |
| `cancelButton`    | `string`         | `''`    | Cancel button label                                                 |
| `requirePassword` | `bool\|string`   | `false` | Require the user to enter their password before executing           |

## Callbacks

### `each` — per-row processing

```php
->bulkAction('Activate', each: fn($user) => $user->update(['active' => true]))
```

Rows are processed in chunks of 1,000 to avoid memory issues.

### `before` and `after` — hooks

```php
->bulkAction(
    label: 'Export',
    before: fn($ids) => logger('Starting export for ' . count($ids) . ' users'),
    each: fn($user) => $user->export(),
    after: fn($ids) => logger('Export complete'),
)
```

## Confirmation Dialog

```php
->bulkAction(
    label: 'Delete',
    each: fn($user) => $user->delete(),
    confirm: 'Are you sure you want to delete the selected users?',
    confirmText: 'This action cannot be undone.',
    confirmButton: 'Yes, delete',
    cancelButton: 'Cancel',
)
```

Pass `true` to use the default translated confirmation message.

## Password Confirmation

Require the user to enter their password before the action executes:

```php
->bulkAction(
    label: 'Permanently Delete',
    each: fn($user) => $user->forceDelete(),
    requirePassword: true,
)
```

## Selecting Rows

In the frontend, users can:

- **Select individual rows** via the row checkbox
- **Select all on this page** via the header checkbox
- **Select all results** (across all pages) via the "Select all N results" prompt that appears after selecting the
  current page

When "all results" is selected, `$ids` will be `['*']` and the backend re-applies all active filters/search before
processing.

## Security

Bulk actions use **signed routes** (Laravel's `URL::signedRoute`). The table class name and action index are
base64-encoded in the URL and verified on the server. Authorization is checked via `AbstractTable::authorize()`.

## Handling in AbstractTable

When using `AbstractTable`, override `performBulkAction` for custom control:

```php
public function performBulkAction(int $key, array $ids): void
{
    match ($key) {
        0 => User::whereKey($ids)->update(['active' => true]),
        1 => User::whereKey($ids)->delete(),
        default => throw new \InvalidArgumentException("Unknown action: {$key}"),
    };
}
```

`$key` is the zero-based index of the action in the order they were registered in `configure()`.

## Multiple Bulk Actions

```php
$table
    ->bulkAction('Activate', each: fn($u) => $u->update(['active' => true]))
    ->bulkAction('Deactivate', each: fn($u) => $u->update(['active' => false]))
    ->bulkAction('Delete', each: fn($u) => $u->delete(), confirm: 'Delete selected?');
```
