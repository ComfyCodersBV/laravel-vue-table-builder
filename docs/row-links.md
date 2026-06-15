# Row Links & Modals

Make table rows clickable so users can navigate to a detail page or open a modal.

## Row Links

Make each row a link to a URL:

```php
$table->rowLink(fn($user) => route('users.show', $user));
```

The callback receives the row item (model instance) and must return a URL string or `null`.

Clicking anywhere on the row (except cells with `clickable: false`) navigates to the URL using an Inertia visit.

## External Row Links (non-Inertia pages)

When the target page is not an Inertia page, use `href: true` to navigate with a plain browser redirect instead of an Inertia visit:

```php
$table->rowLink(fn($ticket) => route('tickets.show', $ticket), href: true);
```

This sets `rowLinkType` to `'href'` and uses `window.location.href` on click.

## Row Modals

Open a modal instead of navigating:

```php
$table->rowModal(fn($user) => route('users.edit', $user));
```

This sets `rowLinkType` to `'modal'`. On click, the package fetches the URL with `Accept: application/json` and passes the response to your application's `openModal` function.

### Host app requirement

The package dispatches a `table-builder:open-modal` DOM event on `window`. Listen for it wherever your modal system lives:

```ts
window.addEventListener('table-builder:open-modal', (e) => {
    openModal((e as CustomEvent).detail);
});
```

The URL must return JSON in this shape:

```json
{
    "component": "MyModal",
    "title": "Optional title",
    "data": {}
}
```

`component` is the modal Vue component name. How you render it is up to your app - a common pattern is a `ModalRenderer` component that resolves `components/${name}.vue` dynamically and renders it inside a `<dialog>` or overlay.

### Signature (rowLink)

```php
rowLink(callable $callback, bool $modal = false, bool $href = false): self
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
2. This array is passed to the frontend alongside `rowLinkType` (`'link'`, `'modal'`, or `'href'`).
3. The Vue component matches each row by index to its link URL and applies the appropriate click handler.
