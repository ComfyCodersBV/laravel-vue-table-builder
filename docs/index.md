# Laravel Vue TableBuilder

A Laravel package for building server-driven, interactive data tables with an Inertia.js + Vue 3 frontend. Define your
table once in PHP — columns, filters, search, sorting, pagination, and bulk actions are all handled automatically.

## Key Features

- **Server-driven** — all filtering, sorting, and pagination handled in PHP
- **Inertia.js integration** — seamless SPA experience without custom AJAX code
- **Column visibility** — users can show/hide columns via a dropdown
- **Filters** — select-based and callback-based filters
- **Global & per-column search** — with configurable wildcard modes
- **Bulk actions** — with optional confirmation dialogs and password prompts
- **Row links & modals** — make any row clickable
- **TypeScript support** — full type definitions included

## Quick Example

**Table class** (`app/Tables/UsersTable.php`):

```php
class UsersTable extends AbstractTable
{
    public function for()
    {
        return User::query();
    }

    public function configure(TableBuilder $table)
    {
        $table
            ->column('id', 'ID', canBeHidden: false, sortable: true)
            ->column('name', 'Name', sortable: true, searchable: true)
            ->column('email', 'Email', sortable: true)
            ->column('company.name', 'Company')
            ->selectFilter('status', ['active' => 'Active', 'inactive' => 'Inactive'])
            ->withGlobalSearch()
            ->bulkAction('Delete', each: fn($user) => $user->delete(), confirm: 'Delete selected users?')
            ->rowLink(fn($user) => route('users.show', $user))
            ->paginate(15)
            ->defaultSort('name');
    }
}
```

**Controller**:

```php
public function index()
{
    return Inertia::render('Users/Index', [
        'table' => UsersTable::build(),
    ]);
}
```

**Vue page**:

```vue
<script setup lang="ts">
import { TableBuilder } from '@/components'
import type { TableData } from '@/types/table-builder'

defineProps<{ table: TableData }>()
</script>

<template>
  <TableBuilder :table="table" />
</template>
```

## How It Works

1. Define a table class extending `AbstractTable`
2. Pass its output to Inertia via `TableBuilder::build()` or `UsersTable::build()`
3. Render `<TableBuilder :table="table" />` in your Vue page
4. All user interactions (sort, filter, search, page) use Inertia visits — no manual AJAX

## Next Steps

- [Installation](installation.md)
- [Table Classes](table-classes.md)
- [Columns](columns.md)
