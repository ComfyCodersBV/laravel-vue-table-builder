# Vue Component

## Import

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

The component is exported from the package's `resources/js/components/` directory, which is aliased via `@`.

## Props

| Prop    | Type        | Required | Description                                                                 |
|---------|-------------|----------|-----------------------------------------------------------------------------|
| `table` | `TableData` | Yes      | The serialized table data from the PHP `TableBuilder`                       |
| `name`  | `string`    | No       | Table name — must match the `name()` set in PHP for query param namespacing |

## Features Rendered

The component renders all of the following automatically based on what the PHP builder provides:

| Feature                             | Rendered when                             |
|-------------------------------------|-------------------------------------------|
| Column headers with sort indicators | columns exist                             |
| Column visibility dropdown          | any column has `can_be_hidden: true`      |
| Filter dropdowns                    | filters array is non-empty                |
| Search inputs                       | searchInputs map is non-empty             |
| Pagination controls                 | `pagination` object is present            |
| Per-page selector                   | `perPageOptions` has more than one option |
| Bulk action toolbar                 | `bulkActions` array is non-empty          |
| Row checkboxes                      | bulk actions present                      |
| Clickable rows                      | `rowLinks` array is non-empty             |
| Reset button                        | any filter/search/sort is active          |

## User Interactions

All interactions make an Inertia visit with `preserveState: true` and `preserveScroll: true` so the page does not fully
reload.

| Interaction                   | Query Param Changed             |
|-------------------------------|---------------------------------|
| Click sortable column header  | `sort=column` or `sort=-column` |
| Toggle column visibility      | `columns[]=key` list            |
| Change filter dropdown        | `filter[key]=value`             |
| Type in search input          | `filter[key]=term` (debounced)  |
| Change page                   | `page=N`                        |
| Change per-page               | `perPage=N`                     |
| Select rows + run bulk action | POST to signed URL              |

## Search Debounce

Search inputs are debounced to avoid firing on every keystroke. The default is 350ms. Change it globally:

```php
TableBuilder::defaultSearchDebounce(500); // ms
```

## Dark Mode

The component respects the `dark` class on the `<html>` element and uses Tailwind's dark mode utilities throughout.

## Customizing Appearance

### Global cell/header classes

```php
$table->class(cell: 'py-3 text-sm', head: 'bg-muted font-semibold');
```

### Per-column classes

```php
->column('id', 'ID', classes: 'w-16 tabular-nums')
```

### CSS Variables

The package uses shadcn/ui-style CSS variables. Override in your `app.css`:

```css
:root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    /* ... */
}
```

## No Slots or Emits

The component has no named slots and emits no events. All interaction is handled internally via Inertia router visits.

## TypeScript

Import types for use in your pages:

```ts
import type { TableData, Column, Filter, BulkAction, PaginationData } from '@/types/table-builder'
```

See [TypeScript Types](typescript.md) for the full interface reference.
