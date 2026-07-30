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

| Parameter         | Type             | Default | Description                                                              |
|-------------------|------------------|---------|--------------------------------------------------------------------------|
| `label`           | `string`         | -       | Button label shown in the UI                                             |
| `each`            | `callable\|null` | `null`  | Called once per selected row; receives the model instance                |
| `before`          | `callable\|null` | `null`  | Called once before processing starts; receives the array of IDs          |
| `after`           | `callable\|null` | `null`  | Called once after all rows are processed; receives the array of IDs      |
| `confirm`         | `bool\|string`   | `''`    | Show a confirmation dialog; pass `true` or a custom message string       |
| `confirmText`     | `string`         | `''`    | Body text inside the dialog                                              |
| `confirmButton`   | `string`         | `''`    | Confirm button label                                                     |
| `cancelButton`    | `string`         | `''`    | Cancel button label                                                      |
| `requirePassword` | `bool\|string`   | `false` | Password field name to send along; `true` becomes `password` (see below) |

## Callbacks

### `each` - per-row processing

```php
->bulkAction('Activate', each: fn($user) => $user->update(['active' => true]))
```

Rows are processed in chunks of 1,000 to avoid memory issues.

### `before` and `after` - hooks

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

Mark an action as requiring the user's password. `true` becomes the field name `password`; pass a string for a custom
field name:

```php
->bulkAction(
    label: 'Permanently Delete',
    each: fn($user) => $user->forceDelete(),
    requirePassword: true,
)

->bulkAction(
    label: 'Permanently Delete',
    each: fn($user) => $user->forceDelete(),
    requirePassword: 'current_password',
)
```

> **Warning:** this only travels along in the `requirePassword` key of the action payload. The bundled Vue component
> does not prompt for a password, and the package does not verify one server-side. Treat it as a hint for your own
> frontend, and check the password yourself in `authorize()` if you depend on it.

## Selecting Rows

In the frontend, users can:

- **Select individual rows** via the row checkbox
- **Select all on this page** via the header checkbox
- **Select all results** (across all pages) via the "Select all N results" prompt that appears after selecting the
  current page

When "all results" is selected, `$ids` will be `['*']` and the backend re-applies all active filters/search before
processing.

## Security

Bulk action URLs are generated with `URL::signedRoute()`, and the table class name and action index are base64-encoded
into them. The route is protected by Laravel's `ValidateSignature` middleware, so a request with a missing, tampered or
expired signature is rejected with a `403` before the controller runs. The signature covers the URL, which means the
table class, the action index and the query string cannot be swapped out.

The signature says the URL came from a page this application rendered. It does not say *who* is calling, and the posted
`ids` are not part of it. Authorization stays your job: `AbstractTable::authorize()` returns `true` by default, so
override it on every table class that exposes bulk actions.

```php
public function authorize(Request $request): bool
{
    return $request->user()?->can('update', User::class) ?? false;
}
```

The package route is registered without a middleware group, so it carries no `web` session or CSRF protection of its
own. If your bulk actions depend on the session user, make sure the route runs inside your application's `web` stack.

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
