# Reordering

Let users drag rows into a new order. Declare the order column in the Table class and the package
stores the new order itself:

```php
public function for(): Builder
{
    return Room::query()
        ->orderBy('sort_order')
        ->orderBy('name');
}

public function configure(TableBuilder $table): void
{
    $table
        ->column('name')
        ->reorderable('sort_order');
}
```

The component puts a drag handle in front of every row. After a drop it posts `{id, to}`, the keys
of the dragged row and of the row it was dropped on, to a signed package route and reloads the table.

## Signature

```php
reorderable(string $column = 'sort_order', ?string $url = null): self
```

| Parameter | Description                                                                                 |
|-----------|---------------------------------------------------------------------------------------------|
| `column`  | The integer column that holds the position                                                  |
| `url`     | A route of your own to post to instead of the package route, see [below](#using-your-own-route) |

## How the Order Is Stored

The server takes every row of the table's `for()` query, without the current search and filters,
and puts them in the order the table shows them:

1. the order column,
2. then the other `orderBy` clauses of the `for()` query, such as `name` above,
3. then the table's `defaultSort()`, when that is another column,
4. then the primary key.

It moves the dragged row to the position of the target and numbers all rows from 1, rewriting only
the rows whose position changed. Pagination does not matter: the keys are global.

Rows that share a position, such as freshly imported rows that are all `0`, are therefore numbered in
the order you see them on the first move, and every move after that works on unique positions. Give
the `for()` query (or `defaultSort()`) the same secondary order the page shows, otherwise a tie is
broken by the primary key and a row can land next to a different neighbour than the one it was
dropped on.

## When the Handles Show

The handles hide while the table is sorted on another column through a column header, because the
order you see is then not the stored one. Sorting on the order column itself keeps them.

Filtering and searching keep the handles. A drop then moves the row in front of (or behind) the row
it was dropped on, and the rows that are filtered out keep their relative order.

## Authorization

The package route rebuilds the Table class from the signed url and calls `authorizeReorder()`, which
falls back to `authorize()`. Run the package routes inside your `web` stack with `auth` so those hooks
see the logged-in user:

```php
public function authorizeReorder(Request $request): bool
{
    return $request->user()->can('update', $this->venue);
}
```

Only call `reorderable()` for users who may reorder, so others do not see the handles; the route
answers `404` for a table that is not reorderable. A table with constructor arguments, like the venue
here, tells the package how to rebuild itself with `signedParameters()` and `fromSignedParameters()`.
See [Security](security.md).

## Using Your Own Route

Point the table at a route of your own when you would rather handle the request in a controller:

```php
$table->reorderable('sort_order', url: route('venues.rooms.reorder', $venue));
```

```php
use TranquilTools\TableBuilder\Http\Requests\ReorderRequest;

public function reorder(ReorderRequest $request, Venue $venue): RedirectResponse
{
    Gate::authorize('update', $venue);

    (new RoomTable($request->user(), $venue))->performReorder($request->rowId(), $request->targetId());

    return back();
}
```

`ReorderRequest` validates `id` and `to`. `performReorder()` uses the same ordering rules as the
package route and throws an `InvalidArgumentException` for a key outside the table's query; turn it
into a validation error if you like:

```php
try {
    $table->performReorder($request->rowId(), $request->targetId());
} catch (InvalidArgumentException $exception) {
    throw ValidationException::withMessages(['id' => $exception->getMessage()]);
}
```

The `TranquilTools\TableBuilder\Actions\ReorderRows` action does the work and can be called on any
query directly. Ties are broken by the `orderBy` clauses already on that query:

```php
app(ReorderRows::class)(Room::query()->orderBy('name'), 'sort_order', 'id', $id, $toId);
```

## Reordering Without the Server

Without `reorderable()` in the Table class you can still show the handles with the `reorderable`
prop and persist the order yourself. Dropping a row emits `reorder` with `{from, to, row}`; `from`
and `to` are positions within the rows currently shown and `row` is the dragged row. The component
does not move anything itself: persist the new order and reload.

```vue
<script setup lang="ts">
import {useTemplateRef} from 'vue'
import {router} from '@inertiajs/vue3'

const lines = useTemplateRef('lines')

function move({row, to}: {from: number; to: number; row: any}) {
    router.post(`/lines/${row.id}/move`, {position: to}, {
        onSuccess: () => lines.value?.reload(),
    })
}
</script>

<template>
    <TableBuilder ref="lines" :table="table" reorderable @reorder="move"/>
</template>
```

On a paginated table add the offset of the current page yourself, and keep in mind that a sorted
or filtered table shows a different order than the one you store.

`reorder` is emitted for server-side reordering too, before the request is sent.

## Payload

The Table class sends `reorder` to the component:

```ts
interface ReorderConfig {
    column: string      // the order column
    key: string         // the row key used for `id` and `to`, see rowKey()
    url: string | null  // the signed package url or your own
}
```
