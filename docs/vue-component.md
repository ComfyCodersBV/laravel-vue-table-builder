# Vue Component

## Import

```vue

<script setup lang="ts">
    import {TableBuilder} from '@/components'
    import type {TableData} from '@/types/table-builder'

    defineProps<{ table: TableData }>()
</script>

<template>
    <TableBuilder :table="table"/>
</template>
```

The component is exported from the package's `resources/js/components/` directory, which is aliased via `@`.

## Props

| Prop    | Type        | Required | Description                                                                                                                                                                                                                                                                          |
|---------|-------------|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `table` | `TableData` | Yes      | The serialized table data from the PHP `TableBuilder`                                                                                                                                                                                                                                |
| `name`  | `string`    | No       | Overrides the table name used to namespace query params (`{name}_page`, `{name}_perPage`). Usually unnecessary: the name is read from the serialized `table` payload (derived from the table class), so it only needs setting for inline tables where you called `->name()` manually |
| `only`  | `string[]`  | No       | Inertia prop name(s) to reload on pagination/per-page changes, enables partial reloads so only this table's data is fetched                                                                                                                                                          |
| `paginationPosition` | `'top'\|'bottom'\|'both'` | No | Where the pagination controls render. Defaults to `bottom`; `top` puts a compact version next to the column selector                                                                                                                             |
| `transport` | `'inertia'\|'http'` | No | How the table fetches its rows. Defaults to `inertia`. See [HTTP Transport](http-transport.md)                                                                                                                                                                       |
| `source` | `string`   | No       | Url the `http` transport fetches from. Required with `transport="http"`                                                                                                                                                                                                             |
| `fetcher` | `TableFetcher` | No  | Replaces the built-in request of the `http` transport                                                                                                                                                                                                                               |
| `adapter` | `TableAdapter` | No  | Maps a non-standard response body onto `{data, pagination}`                                                                                                                                                                                                                         |
| `reorderable` | `boolean` | No   | Adds a drag handle in front of every row. See [Reordering](reordering.md)                                                                                                                                                                                                  |
| `rowClass` | `(row, index) => string \| string[] \| Record<string, boolean>` | No | Extra classes per row, for status colours and similar. Merged with the classes of `$table->rowClass()`, the prop last so it wins a conflicting Tailwind class. See [Row classes](#row-classes)                                       |
| `emptyState` | `EmptyState` | No | Texts for an empty table, overriding the ones from the Table class. See [Empty State](empty-state.md)                                                                                                                                                              |
| `confirm` | `(message, details?) => boolean \| Promise<boolean>` | No | Asks the confirmation of a row or bulk action. Defaults to `window.confirm()`. A bulk action passes `details` with its `text`, `confirmButton` and `cancelButton`. See [Row Actions](row-actions.md) and [Bulk Actions](bulk-actions.md)                                                                                                                                       |

## Features Rendered

The component renders all of the following automatically based on what the PHP builder provides:

| Feature                             | Rendered when                             |
|-------------------------------------|-------------------------------------------|
| Column headers with sort indicators | columns exist                             |
| Column visibility dropdown          | any column has `can_be_hidden: true` and `columnToggle` is not `false` |
| Filter dropdowns                    | filters array is non-empty                |
| Search inputs                       | searchInputs map is non-empty             |
| Pagination controls                 | `pagination` object is present            |
| Per-page selector                   | `perPageOptions` has more than one option |
| Bulk action toolbar                 | `bulkActions` array is non-empty          |
| Row checkboxes                      | bulk actions present                      |
| Clickable rows                      | `rowLinks` array is non-empty, or a `row-click` listener is bound |
| Drag handles                        | `reorderable` is set                      |
| Totals footer                       | `totals` is set and the table has rows    |
| Export button                       | `export.url` is set                       |
| Reset button                        | any filter/search/sort is active          |

## User Interactions

With the default `inertia` transport every interaction makes an Inertia visit with `preserveState: true` and
`preserveScroll: true` so the page does not fully reload. With the `http` transport the same interactions change the
in-memory query and refetch from `source` instead; the url is left alone. See [HTTP Transport](http-transport.md).

| Interaction                   | Query Param Changed                 |
|-------------------------------|-------------------------------------|
| Click sortable column header  | `sort=column` or `sort=-column`     |
| Toggle column visibility      | `columns[]=key` list                |
| Change filter dropdown        | `filter[key]=value`                 |
| Type in search input          | `filter[key]=term` (debounced)      |
| Change page                   | `{name}_page=N` (or `page=N`)       |
| Change per-page               | `{name}_perPage=N` (or `perPage=N`) |
| Select rows + run bulk action | POST to signed URL                  |

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
->column('id', 'ID', classes: ['w-16' => true, 'hidden' => $compact])
```

The resolved classes arrive in the `class` key of the column payload.

### CSS Variables

The package uses shadcn/ui-style CSS variables. Override in your `app.css`:

```css
:root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    /* ... */
}
```

### Row classes

Style rows from the table class with `rowClass()`. The callback gets the original row (the model, not the formatted
array) and returns a string, a list of classes or a `class => bool` array:

```php
$table->rowClass(fn (Message $message): array => [
    'font-semibold' => ! $message->is_seen,
    'text-muted-foreground' => $message->is_archived,
]);
```

The result travels as `__class` on each row, only for rows with a class and only when `rowClass()` is set. A row that
already has a `__class` value throws a `LogicException`; alias that column in the query. The component merges it
with the `rowClass` prop through `tailwind-merge`, prop last.

## Auto-refresh

`$table->poll(30)` sends `poll: 30`, and the component reloads the rows every 30 seconds: with the `inertia`
transport through `router.reload()` (pass `only` so only the table prop is fetched), with the `http` transport by a
silent refetch of the current query. Sorting, search, filters, the page and the selection stay as they are.

It pauses while rows are selected, while a confirmation or choice dialog is open, while an action or request runs
and while the tab is hidden. A tick missed while the tab was hidden runs as soon as it is visible again. Unmounting
stops the timer. Without `poll()` the payload has no `poll` key and nothing is scheduled.

## Translations

The component reads its texts from the shared Inertia prop `vue_table_builder_table_translations`. `useTranslations` is
exported from the package entry `resources/js/index.ts`, here aliased as `@table-builder`:

```ts
import {useTranslations} from '@table-builder'

const {t} = useTranslations('vue_mailbox_translations', {group: 'mailbox'})

t('mailbox::mailbox.compose.title')
t('compose.title')
```

Share the bundle from your own service provider, for the current locale:

```php
Inertia::share('vue_mailbox_translations', fn () => trans('mailbox::mailbox'));
```

A `vendor::` prefix is always stripped. `group` strips the file name the bundle was shared from; it defaults to
`table` for the table builder's own bundle and to nothing for any other.

## Slots

| Slot          | Scope                          | Renders                                                    |
|---------------|--------------------------------|------------------------------------------------------------|
| `cell-{key}`  | `row`, `value`, `index`, `column` | Replaces the cell body of the column named `{key}`      |
| `actions`     | `row`, `index`, `actions`, `run` | A trailing column on every row, right aligned; clicks do not follow the row link. Replaces the buttons of [Row Actions](row-actions.md) |
| `empty`       | `filtered`, `title`, `description`, `reset` | The content of the row shown when there are no rows. See [Empty State](empty-state.md) |
| `toolbar`     | -                              | Controls next to the column selector above the table        |
| `row-after`   | `row`, `index`, `columns`, `colspan` | Extra rows under each row. See [Nested Rows](#nested-rows) |
| `total-{key}` | `value`, `column`, `totals`    | Replaces the total of the column named `{key}` in the footer. See [Totals](totals.md) |

```vue

<TableBuilder :table="table">
    <template #cell-status="{value}">
        <Badge :variant="value === 'active' ? 'default' : 'secondary'">{{ value }}</Badge>
    </template>

    <template #actions="{row}">
        <Button size="sm" @click="edit(row)">Edit</Button>
    </template>

    <template #toolbar>
        <Button variant="outline" @click="openSettings">Settings</Button>
    </template>
</TableBuilder>
```

Without a `cell-{key}` slot the value is rendered with `v-html`. The PHP builder escapes string
values before they are serialized, so that is safe for tables driven by the builder. It is not safe
for rows fetched from an API with the `http` transport: escape those yourself, or render them
through a `cell-{key}` slot.

## Events

| Event       | Payload                  | Fired when                                                                 |
|-------------|--------------------------|----------------------------------------------------------------------------|
| `reorder`   | `{from, to, row}`        | A row is dropped on another row of a `reorderable` table                   |
| `row-click` | `{row, index, key, event}` | A row without a row link is clicked; `key` is the row's [row key](bulk-actions.md#row-key) value |

### Reordering Rows

`reorderable()` in the Table class, or the `reorderable` prop, puts a drag handle in front of every
row and emits `reorder` with `{from, to, row}` after a drop. See [Reordering](reordering.md) for
storing the order on the server, how ties are broken and handling the event yourself.

### Clicking Rows

A row with a row link from the PHP builder follows that link. Every other row emits `row-click`, and
gets the pointer cursor as soon as a listener is bound, so an `http` table can open a record without
a server-side row link. Clicks on the drag handle, the selection checkbox, the `actions` slot and
columns marked `clickable: false` never reach it.

```vue

<TableBuilder :table="table" @row-click="({row}) => open(row)"/>
```

### Nested Rows

The `row-after` slot renders below each row, for option lines or grouped sub-rows. `colspan` is the
full width of a row including the handle, selection and actions columns, and `columns` are the
visible columns. `TableRow` and `TableCell` are exported so those rows look like the rest of the
table.

```vue

<script setup lang="ts">
    import {TableBuilder, TableCell, TableRow} from '@/components'
</script>

<template>
    <TableBuilder :table="table">
        <template #row-after="{row, colspan}">
            <TableRow v-for="option in row.options" :key="option.id" class="bg-muted/30">
                <TableCell :colspan="colspan" class="pl-10">{{ option.name }}</TableCell>
            </TableRow>
        </template>
    </TableBuilder>
</template>
```

## Exposed Methods

```vue

<script setup lang="ts">
    import {useTemplateRef} from 'vue'

    const table = useTemplateRef('table')

    function refresh() {
        table.value?.reload()
    }
</script>

<template>
    <TableBuilder ref="table" :table="table"/>
</template>
```

| Method           | Returns      | Description                                                          |
|------------------|--------------|----------------------------------------------------------------------|
| `reload()`       | `void`       | Refetches the rows (`http`) or reloads the Inertia props (`inertia`) |
| `currentQuery()` | `TableQuery` | The active sort, filters, search, page and per-page                  |
| `exportUrl()`    | `string \| null` | The signed export url with the current state appended. See [Export](export.md) |

## TypeScript

Import types for use in your pages:

```ts
import type {TableData, Column, Filter, BulkAction, PaginationData} from '@/types/table-builder'
```

See [TypeScript Types](typescript.md) for the full interface reference.
