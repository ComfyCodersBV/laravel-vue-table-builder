# Installation

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 13
- Inertia.js with Vue 3
- Tailwind CSS v4

## Composer

```bash
composer require tranquil-tools/laravel-vue-table-builder
```

The service provider registers automatically via Laravel's package discovery.

## Publish Assets

Publish translations (optional):

```bash
php artisan vendor:publish --tag="vue-table-builder-translations"
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="vue-table-builder-config"
```

## NPM Dependencies

The package ships its Vue component and UI primitives. Install peer dependencies:

```bash
npm install lucide-vue-next
```

## Import CSS

Import the package stylesheet in your app entry point (`resources/js/app.js` or `resources/js/app.ts`):

```js
import '../css/app.css'
```

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
