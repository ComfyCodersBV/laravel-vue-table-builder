# Installation

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 13
- Inertia.js with Vue 3
- Tailwind CSS v4.1 or newer (the touch sizes use the `pointer-coarse` variant)

## Composer

```bash
composer require tranquil-tools/laravel-vue-table-builder
```

The service provider registers automatically via Laravel's package discovery. The PHP side (table classes, endpoints,
config and translations) lives in `tranquil-tools/laravel-table-builder`, which comes along as a dependency; this
package adds the Vue component and shares the translations with Inertia.

## Publish Assets

Publish translations (optional):

```bash
php artisan vendor:publish --tag="table-builder-translations"
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="table-builder-config"
```

Before 1.9 these were `vue-table-builder-translations` and `vue-table-builder-config`. A published
`config/vue-table-builder.php` or `lang/vendor/vue-table-builder` keeps working, and `config('vue-table-builder.*')` and
`vue-table-builder::` keys keep resolving.

## NPM Dependencies

The package ships its Vue component and UI primitives. Install peer dependencies:

```bash
npm install reka-ui @lucide/vue
```

The components import their icons from `@lucide/vue`, the maintained successor of `lucide-vue-next` that Laravel's
starter kits ship. An app that still only has `lucide-vue-next` can install `@lucide/vue` next to it, or alias it in
Vite: `'@lucide/vue': 'lucide-vue-next'`.

## Import CSS

Import the package stylesheet in your app entry point (`resources/js/app.js` or `resources/js/app.ts`):

```js
import '../css/app.css'
```

## Tailwind CSS

The component's classes live in its `.vue` files. Let Tailwind scan them, next to your own sources, in the stylesheet
that imports Tailwind:

```css
@source '../../vendor/tranquil-tools/laravel-vue-table-builder/resources/js/**/*.vue';
```

Without it, classes that only the package uses, such as `hidden md:table-cell` for
[`hideBelow`](columns.md#hiding-a-column-on-small-screens), are missing from the build.

## Touch Screens

On a touch screen (a coarse pointer) the controls of the table grow to at least 40px: the toolbar buttons, the search
input, the filter inputs and selects, the sort select and sortable headers, the layout switch, the pagination, the
bulk action menu and choice dialog, the row action buttons, the menu items and the hit area of the selection
checkboxes. With a mouse the table keeps its compact sizes.

## Dark Mode

Dark mode is supported out of the box. Add the `dark` class to your `<html>` element to enable it:

```html
<html class="dark">
```

## Modal Support (optional)

If you use `->rowModal()`, the package dispatches a `table-builder:open-modal` DOM event on `window`. Listen for it wherever your modal system lives:

```ts
window.addEventListener('table-builder:open-modal', (e) => {
    openModal((e as CustomEvent).detail);
});
```

See [Row Links & Modals](row-links.md) for the full payload shape and contract.

## Generate a Table Class

Use the Artisan command to scaffold a table class:

```bash
php artisan make:table UsersTable
```

This creates `app/Tables/UsersTable.php` with an `AbstractTable` stub ready to configure.
