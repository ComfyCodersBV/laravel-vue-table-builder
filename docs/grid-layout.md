# Grid Layout

A table can render its rows as tiles instead of table rows, for example for a list of rooms, products or people
where a card reads better than a row. The PHP table stays the same: columns, filters, search, sorting, pagination,
row links, row actions and bulk actions all keep working.

```php
public function configure(TableBuilder $table): void
{
    $table
        ->grid()
        ->column('name', 'Name', sortable: true)
        ->column('city', 'City')
        ->column('capacity', 'Capacity', sortable: true)
        ->rowAction('Edit', url: fn (Room $room) => route('rooms.edit', $room), icon: 'pencil')
        ->rowAction('Delete', url: fn (Room $room) => route('rooms.destroy', $room), method: 'delete', icon: 'trash-2')
        ->paginate(12);
}
```

`->grid()` is short for `->layout('grid')`. `->layout('table')` is the default.

## Letting the User Switch

`->layoutToggle()` adds a list/grid switch to the toolbar. The configured layout is the default; the user's choice
lives in the `layout` query key, prefixed like the other keys of the table (`rooms_layout=grid`), so a refresh, the
back button and a shared link show the same layout. The url is updated with an Inertia `router.replace`, so
switching adds no history entry, and the key is removed again when the user picks the default. An unknown value in
the url is ignored. With the [http transport](http-transport.md) the url is left alone, like the other table state,
and the choice lasts until the page reloads.

```php
$table->layoutToggle();          // list by default, the user may switch to tiles
$table->grid()->layoutToggle();  // tiles by default, the user may switch to the list
```

## The Default Tile

- The first visible column is the title, the other visible columns are small `Label: value` lines. The columns
  menu still hides and shows them.
- `cell-{key}` slots render in the tile too, so a badge or an image you already render in a cell shows up in the
  tile without extra work.
- Row actions sit in the footer of the tile. An action with a registered icon renders as an icon-only button with
  its label as `aria-label` and tooltip; an action without an icon keeps its label. The `#actions` slot replaces
  them, as in the table.
- With bulk actions, every tile gets a checkbox in its top right corner and a selected tile gets a ring. Above the
  grid a select menu offers the same choices as the header checkbox of the table: this page, all results or clear.
- A row link makes the whole tile clickable and a hidden link inside it reachable with Tab and Enter, like a row.
  A `row-click` listener works the same way.
- The grid has no column headers, so a sort select appears in the toolbar when the table has sortable columns. It
  lists every sortable column ascending and descending, and a "Default order" option when the table has no
  `defaultSort`.

## Grid Columns

By default the grid shows 2 tiles per row on phones, 3 from `md`, 4 from `lg`, 5 from `xl` and 6 from `2xl`.
Pass your own Tailwind classes with the `grid-class` prop:

```vue
<TableBuilder :table="rooms" grid-class="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3"/>
```

## Your Own Tile

The `#tile` slot replaces the content of a tile. The tile itself stays: it still follows the row link, carries the
row classes and holds the selection checkbox, so leave some room in its top right corner when the table has bulk
actions.

```vue
<TableBuilder :table="rooms">
    <template #tile="{row, actions, run}">
        <img :src="row.photo_url" alt="" class="mb-2 aspect-video w-full rounded object-cover"/>
        <p class="font-medium">{{ row.name }}</p>
        <p class="text-xs text-muted-foreground">{{ row.city }}</p>
        <div class="mt-auto flex justify-end gap-1 pt-3">
            <RowActionButtons :actions="actions" icon-only @run="run"/>
        </div>
    </template>
</TableBuilder>
```

| Slot prop  | Description                                                                         |
|------------|-------------------------------------------------------------------------------------|
| `row`      | The row, with its values as the PHP builder serialized them                         |
| `index`    | Position of the row on the page                                                     |
| `columns`  | The visible columns                                                                 |
| `actions`  | The row actions of this row                                                         |
| `run`      | `run(action, event?)` runs a row action, with its confirm question and busy guard   |
| `selected` | Whether the row is selected for a bulk action                                       |
| `link`     | The row link, or `null`                                                             |

`RowActionButtons` is the component that renders the row action buttons in the table and the tile; import it from
the package to reuse it. Its `icon-only` prop makes every action with a registered icon an icon-only button. A click on a
button, link or input inside the tile does not follow the row link, only the control acts.

## What Stays Table-Only

Drag-to-reorder, the totals footer and the `row-after` slot render in the table layout only. Switch a table with
those features to the grid and they are left out.

Requires `tranquil-tools/laravel-table-builder` 1.3.
