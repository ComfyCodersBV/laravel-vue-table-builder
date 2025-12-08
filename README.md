# A VueJS/Inertia TableBuilder package for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/comfycoders/laravel-vue-table-builder.svg?style=flat-square)](https://packagist.org/packages/comfycoders/laravel-vue-table-builder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/comfycoders/laravel-vue-table-builder/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/comfycoders/laravel-vue-table-builder/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/comfycoders/laravel-vue-table-builder/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/comfycoders/laravel-vue-table-builder/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/comfycoders/laravel-vue-table-builder.svg?style=flat-square)](https://packagist.org/packages/comfycoders/laravel-vue-table-builder)

A powerful and flexible table builder package for Laravel with Vue 3, Inertia.js, and shadcn-vue components. Similar to Laravel Splade tables but built for modern Vue 3 applications with beautiful UI components.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-vue-table-builder.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-vue-table-builder)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require comfycoders/laravel-vue-table-builder
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-vue-table-builder-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-vue-table-builder-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-vue-table-builder-views"
```

## Usage

### Backend (Laravel)

Create a table class:

```php
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use App\Models\User;

class UsersTable extends AbstractTable
{
    public function for()
    {
        return User::query();
    }

    public function configure(TableBuilder $table)
    {
        $table
            ->column('id', 'ID')
            ->column('name', 'Name', sortable: true)
            ->column('email', 'Email', sortable: true)
            ->column('created_at', 'Created', sortable: true)
            ->paginate(15);
    }
}
```

In your controller:

```php
use Inertia\Inertia;

public function index()
{
    return Inertia::render('Users/Index', [
        'table' => UsersTable::build(),
    ]);
}
```

### Frontend (Vue)

Import the TableBuilder component:

```vue
<script setup lang="ts">
import { TableBuilder } from '@/components'
import type { TableData } from '@/types/table-builder'

defineProps<{
  table: TableData
}>()
</script>

<template>
  <div>
    <h1>Users</h1>
    <TableBuilder :table="table" />
  </div>
</template>
```

### Features

- 🎨 Beautiful UI with shadcn-vue table components
- 🔍 Sortable columns with visual indicators
- 📄 Pagination with Inertia.js optimization
- 🎯 Nested relationship support (e.g., `user.company.name`)
- 🚀 Built with TypeScript for type safety
- ⚡ Optimized navigation with preserve-state and preserve-scroll
- 📱 Fully responsive design

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [ComfyCoders](https://github.com/comfycoders)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
