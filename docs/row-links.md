# Row Links & Modals

Make table rows clickable so users can navigate to a detail page or open a modal.

## Row Links

Make each row a link to a URL:

```php
$table->rowLink(fn($user) => route('users.show', $user));
```

The callback receives the row item (model instance) and must return a URL string or `null`.

Clicking anywhere on the row (except cells with `clickable: false`) navigates to the URL using an Inertia visit.
Clicks on a button, link, input or other control inside a cell do their own work and do not follow the row link.

## Keyboard and Screen Readers

The first cell of a linked row holds a real `<a href>` (`data-row-link`), visually hidden. Tab reaches it, Enter
follows the row link the same way a click does, and the row is highlighted while the link has keyboard focus. Its
text is the plain text of the first column, or "row 2" (the `row` translation and the row number) when that column is
empty or a boolean column, so a screen reader announces the row by name. A ctrl-, cmd-, shift-, alt- or middle click
on that link is left to the browser, so it opens in a new tab or window.

A row modal gets a visually hidden `<button type="button">` instead, because its URL returns JSON rather than a page.

When the value of the first column already contains an `<a>`, for example from an `as` callback, the hidden link is
left out so the row does not get two tab stops. A `cell-{key}` slot on the first column that renders its own link
gets both; point that link at the row link, or put it in another column.

## External Row Links (non-Inertia pages)

When the target page is not an Inertia page, use `href: true` to navigate with a plain browser redirect instead of an Inertia visit:

```php
$table->rowLink(fn($ticket) => route('tickets.show', $ticket), href: true);
```

This sets `rowLinkType` to `'href'` and uses `window.location.href` on click.

## Opening a Row Link in a New Tab

Rows navigate in the current tab by default. For links that belong somewhere else - a report,
an external dashboard - pass `newTab: true`:

```php
$table->rowLink(fn($environment) => route('accesslogs.show', $environment), href: true, newTab: true);
```

The new tab wins over the row link type, so it works alongside `href` as well as on its own.

The row itself is a `<tr>`, not an `<a>`: a middle click or ctrl-click with the mouse on the row does not open a new
tab, and hovering shows no URL in the status bar. The hidden link in the first cell covers keyboard and screen reader
use. When a visible anchor matters, render one in the column itself with an `as` callback.

## Row Modals

Open a modal instead of navigating:

```php
$table->rowModal(fn($user) => route('users.edit', $user));
```

This sets `rowLinkType` to `'modal'`. The first cell holds a hidden button that Tab and Enter reach (see
[Keyboard and Screen Readers](#keyboard-and-screen-readers)). On click, the package fetches the URL with `Accept: application/json` and passes the response to your application's `openModal` function.

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
rowLink(callable $callback, bool $modal = false, bool $href = false, bool $newTab = false): self
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

## Row Clicks Without a Link

Rows without a row link emit a `row-click` event from the Vue component instead. See
[Vue Component](vue-component.md#clicking-rows).

## Primary Key

The row link system needs to identify each row. For Eloquent models this is automatic. For plain arrays or models with a
non-standard primary key, configure it explicitly:

```php
$table->rowKey('uuid');
```

`rowKey()` and the older `primaryKey()` set the same thing: the field the component uses for selection, bulk actions,
reordering and the `key` of `row-click`. See [Bulk Actions](bulk-actions.md#row-key).

## How It Works

1. After loading the resource, the table maps `rowLinkCallable` over every row to produce a `rowLinks` array.
2. This array is passed to the frontend alongside `rowLinkType` (`'link'`, `'modal'`, or `'href'`)
   and `rowLinkTarget` (`'_self'` or `'_blank'`).
3. The Vue component matches each row by index to its link URL and applies the appropriate click handler.
