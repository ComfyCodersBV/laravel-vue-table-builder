# Row Links & Modals

Make table rows clickable so users can navigate to a detail page or open a modal.

## Row Links

Make each row a link to a URL:

```php
$table->rowLink(fn($user) => route('users.show', $user));
```

The callback receives the row item (model instance) and must return a URL string or `null`.

Clicking anywhere on the row (except cells with `clickable: false`) navigates to the URL using an Inertia visit.

## Row Modals

Open a modal instead of navigating:

```php
$table->rowModal(fn($user) => route('users.edit', $user));
```

This sets `rowLinkType` to `'modal'` and calls your application's `openModal` composable with the resolved URL.

### Signature (rowLink)

```php
rowLink(callable $callback, bool $modal = false): self
```

### Signature (rowModal)

```php
rowModal(callable $callback): self
```

## Conditional Links

Return `null` from the callback to make a specific row non-clickable:

```php
$table->rowLink(function ($user) {
    if (! auth()->user()->can('view', $user)) {
        return null;
    }
    return route('users.show', $user);
});
```

## Non-Clickable Cells

Individual columns can opt out of following the row link. This is useful for action columns (edit/delete buttons):

```php
->column('actions', 'Actions', clickable: false, canBeHidden: false)
```

## Primary Key

The row link system needs to identify each row. For Eloquent models this is automatic. For plain arrays or models with a
non-standard primary key, configure it explicitly:

```php
$table->primaryKey('uuid');
```

## How It Works

1. After loading the resource, the table maps `rowLinkCallable` over every row to produce a `rowLinks` array.
2. This array is passed to the frontend alongside `rowLinkType` (`'link'` or `'modal'`).
3. The Vue component matches each row by index to its link URL and applies the appropriate click handler.
