# Laravel Vue TableBuilder - Usage Guide

## Installation

### 1. Install the package

```bash
composer require tranquil-tools/laravel-vue-table-builder
```

### 2. Install NPM dependencies

```bash
npm install
```

The required dependencies are already listed in package.json.

### 3. Import CSS

Import the CSS in your main app file:

```js
// resources/js/app.js or app.ts
import '../css/app.css'
```

### 4. Configure path aliases

Make sure your build tool (Vite/Webpack) is configured with the `@` alias pointing to `resources/js`:

```js
// vite.config.js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
})
```

## Basic Usage

### Backend (Laravel)

Create a table class extending `AbstractTable`:

```php
<?php

namespace App\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

class UsersTable extends AbstractTable
{
    public function for(): Builder
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
<?php

namespace App\Http\Controllers;

use App\Tables\UsersTable;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'table' => UsersTable::build(),
        ]);
    }
}
```

### Frontend (Vue)

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { TableBuilder } from '@/components'
import type { TableData } from '@/types/table-builder'

defineProps<{
  table: TableData
}>()
</script>

<template>
  <Head title="Users" />
  
  <div class="container mx-auto py-10">
    <h1 class="text-2xl font-bold mb-6">Users</h1>
    <TableBuilder :table="table" />
  </div>
</template>
```

## Advanced Usage

### Nested Relationships

Access nested relationships using dot notation:

```php
$table
    ->column('id', 'ID')
    ->column('name', 'Name', sortable: true)
    ->column('company.name', 'Company', sortable: true)
    ->column('company.address.city', 'City');
```

### Custom Column Classes

Add custom CSS classes to columns:

```php
$table
    ->column('id', 'ID', class: 'w-20')
    ->column('name', 'Name', headerClass: 'bg-blue-100');
```

### Row Actions

Buttons per row, defined in PHP and rendered without an `#actions` slot. Hidden actions are not sent
to the browser, disabled ones show their reason as a tooltip:

```php
$table
    ->rowAction('Edit', url: fn (Room $room) => route('rooms.edit', $room))
    ->rowAction('Delete', url: fn (Room $room) => route('rooms.destroy', $room), method: 'delete',
        confirm: 'Delete this room?', variant: 'destructive',
        visible: fn (Room $room) => $user->can('delete', $room),
        disabled: fn (Room $room) => $room->units_count > 0 ? 'The room still has units' : false);
```

`confirm` also takes a callback that gets the row, for a question that names it:
`confirm: fn (Room $room) => "Delete {$room->name}?"`. `icon: 'pencil'` shows a registered icon before the label.
While an action runs, the row actions of the table are disabled and the clicked button shows a spinner, so a double
click sends one request.

See [docs/row-actions.md](docs/row-actions.md).

### Empty State

```php
$table->emptyState(title: 'No rooms yet', description: 'Add the first room.', filteredTitle: 'No rooms match');
```

The component also takes an `emptyState` prop and an `#empty` slot. See [docs/empty-state.md](docs/empty-state.md).

### Persisting a Drag-and-Drop Order

```php
$table->reorderable('sort_order');                                   // signed package route
$table->reorderable('sort_order', url: route('rooms.reorder', $venue)); // your own route
```

See the reordering section of [docs/vue-component.md](docs/vue-component.md).

### Date Filters

```php
$table->dateFilter('created_at');
$table->dateRangeFilter('period', column: 'starts_at');   // ?filter[period]=2026-10-01..2026-10-31
```

### Query Keys

```php
$table->filterQueryKey('f');   // ?f[status]=open instead of ?booking_filter[status]=open
$table->queryPrefix('');       // no prefix on sort, filter, page, perPage and columns
```

### Expected Data Structure

The TableBuilder component expects data in this format:

```typescript
{
  data: [
    { id: 1, name: 'John Doe', email: 'john@example.com' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com' },
  ],
  columns: [
    { key: 'id', label: 'ID', sortable: false },
    { key: 'name', label: 'Name', sortable: true, sorted: 'asc' },
    { key: 'email', label: 'Email', sortable: true },
  ],
  pagination: {
    current_page: 1,
    from: 1,
    to: 15,
    total: 50,
    per_page: 15,
    last_page: 4,
    links: [...],
    next_page_url: '/users?page=2',
    prev_page_url: null,
  }
}
```

## Sorting

Sorting is handled automatically when you mark columns as sortable. Click on column headers to toggle between:
- Ascending (↑)
- Descending (↓)
- No sort (default)

The component uses Inertia.js to make requests with `preserve-state` and `preserve-scroll` for a smooth user experience.

## Pagination

Pagination is automatically rendered when the data includes pagination information. The component will show:
- Current page and total pages
- Previous/Next navigation buttons
- Total results count

## Customization

### Styling

The table uses shadcn-vue components with Tailwind CSS. You can customize the theme by modifying the CSS variables in `resources/css/app.css`:

```css
:root {
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  /* ... other variables */
}
```

### Dark Mode

Dark mode is supported out of the box. Add the `dark` class to your HTML element to enable it:

```html
<html class="dark">
```

## Troubleshooting

### TypeScript errors

Make sure your `tsconfig.json` includes the path alias:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./resources/js/*"]
    }
  }
}
```

### Icons not showing

Ensure `@lucide/vue` is installed:

```bash
npm install @lucide/vue
```

### Styles not applying

1. Make sure Tailwind CSS is properly configured
2. Import the CSS file in your app entry point
3. Run `npm run dev` to compile assets
