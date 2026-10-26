# Row Actions

Define the buttons of every row in the Table class. The component renders them in a trailing
column, so a page needs no `#actions` slot.

```php
$table
    ->rowAction('Edit', url: fn (Room $room) => route('rooms.edit', $room))
    ->rowAction(
        'Delete',
        url: fn (Room $room) => route('rooms.destroy', $room),
        method: 'delete',
        confirm: 'Delete this room?',
        variant: 'destructive',
        visible: fn (Room $room) => $viewer->can('delete', $room),
        disabled: fn (Room $room) => $room->units_count > 0 ? 'The room still has units' : false,
    );
```

## Signature

```php
rowAction(
    string $label,
    string|Closure|null $url = null,
    ?string $route = null,
    string $method = 'get',
    string|Closure|null $confirm = null,
    string $variant = 'outline',
    bool|Closure $visible = true,
    bool|string|Closure $disabled = false,
    ?string $key = null,
    bool|string $download = false,
    string|Closure|null $icon = null,
): self
```

| Parameter  | Description                                                                                                  |
|------------|--------------------------------------------------------------------------------------------------------------|
| `label`    | Button text                                                                                                  |
| `url`      | A fixed url, or a callback receiving the row item. Returning `null` hides the action for that row            |
| `route`    | A route name resolved as `route($route, $item)`; use `url` when the route needs more parameters              |
| `method`   | `get`, `post`, `put`, `patch` or `delete`                                                                     |
| `confirm`  | Question asked before the action runs, or a callback receiving the row item. `null` or `''` asks nothing     |
| `variant`  | Button variant: `default`, `destructive`, `outline`, `secondary`, `ghost` or `link`                          |
| `visible`  | `false` or a callback returning `false` leaves the action out of the payload for that row                    |
| `disabled` | `true`, or a callback returning `true` or a reason string. The reason is shown as the button's tooltip      |
| `key`      | Identifier of the action, defaults to the slug of the label. Registering a key again replaces the action     |
| `download` | `true` or a file name: the action downloads a file instead of visiting a page. Only with the `get` method    |
| `icon`     | Name of an icon shown before the label, or a callback receiving the row item. `null` or `''` shows none       |

`url` or `route` is required.

## Confirmation Per Row

A callback for `confirm` gets the row item, like `url`, `visible` and `disabled`, so the question can
name the row. It is resolved on the server, the payload carries the resulting string:

```php
$table->rowAction(
    'Disconnect',
    url: fn (App $app) => route('apps.disconnect', $app),
    method: 'delete',
    confirm: fn (App $app) => "Disconnect {$app->name}?",
);
```

Return `null` or an empty string to run the action on that row without asking. Requires
`tranquil-tools/laravel-table-builder` 1.1.

## Icons

An action can show an icon before its label. PHP sends only a name; the page decides which component it stands for,
so the bundle only holds the icons you use. Register them once, for example with the Lucide names, before the app
mounts:

```php
$table
    ->rowAction('Enable', url: fn (Tool $tool) => route('tools.enable', $tool), method: 'put', icon: 'power')
    ->rowAction('Edit', url: fn (Tool $tool) => route('tools.edit', $tool), icon: fn (Tool $tool) => $tool->locked ? 'lock' : 'pencil');
```

```ts
import {Lock, Pencil, Power} from '@lucide/vue'
import {registerRowActionIcons} from '@table-builder'

registerRowActionIcons({power: Power, lock: Lock, pencil: Pencil})
```

A single table can pass or override icons with the `rowActionIcons` prop:
`<TableBuilder :table="table" :row-action-icons="{power: Power}"/>`. The icon is `aria-hidden` and the label stays,
so screen readers read the same button as before. An unknown name renders the label only. A custom `#actions` slot
can look an icon up with `resolveRowActionIcon(action.icon)`.

The resolved action carries `icon` only when it has one. Requires `tranquil-tools/laravel-table-builder` 1.2.

## Authorization

Hidden actions are never sent to the browser, so `visible` is the place for policy checks. Keep the
same check on the route the action points to: hiding a button does not protect the endpoint.

Use `disabled` with a reason when the user should see that the action exists but cannot use it now.

## How They Run

- `get` actions render as a link. A click visits the url through Inertia; ctrl, cmd and middle
  clicks open a new tab as usual.
- Other methods are sent as an Inertia visit with that method and `preserveScroll`. With the `http`
  transport they are sent with `fetch()` and the table reloads afterwards.
- `confirm` uses `window.confirm()` unless the component gets a `confirm` prop:

```vue
<TableBuilder :table="table" :confirm="(message) => dialog.ask(message)"/>
```

The prop may return a boolean or a promise of one. Bulk actions use the same prop and pass a second `details` argument; see
[Bulk Actions](bulk-actions.md#confirmation-dialog).

## While an Action Runs

A table runs one row action at a time, so a double click sends one request:

- From the click until the request is done, every row action of the table is disabled: the buttons get `disabled`,
  the `get` and download links get `aria-disabled="true"` and ignore clicks. That includes the time the confirm
  question is open, so a second click does not ask twice.
- While the request of a non-`get` action runs, the clicked button gets `aria-busy="true"` and a spinning icon
  (`data-row-action-spinner`) in place of its own icon.
- The actions are enabled again when the Inertia visit finishes, fails or is cancelled, or when the `fetch()` of the
  `http` transport settles. A cancelled or failed confirm question enables them right away.
- A `get` action and a download hold the guard only while their confirm question is open: the visit leaves the page,
  and a second Inertia visit replaces the first.

## Downloads

A file download, such as an invoice PDF, must not go through an Inertia visit: Inertia expects a page
back and shows the file as an error. Mark the action with `download`:

```php
$table->rowAction(
    'PDF',
    url: fn (Invoice $invoice) => route('invoices.pdf', $invoice),
    download: true,
);

$table->rowAction('CSV', url: fn (Report $report) => route('reports.csv', $report), download: 'report.csv');
```

The component renders a plain link with a `download` attribute, so the browser fetches the file
itself and the page stays where it is. A string sets the suggested file name; with `true` the
`Content-Disposition` header of the response decides, which is what `response()->download()` and
`Storage::download()` send. A download with a `confirm` question asks first and then starts the
download. The `run` function of the `#actions` slot downloads too.

The resolved action carries `download: true` or the file name; other actions have no `download` key.

## Custom Rendering

An `#actions` slot still wins over the built-in buttons. It receives the resolved actions of the
row and a `run` function, so you can restyle them and keep the behaviour:

```vue
<TableBuilder :table="table">
    <template #actions="{actions, run}">
        <DropdownMenu>
            <DropdownMenuItem
                v-for="action in actions"
                :key="action.key"
                :disabled="action.disabled"
                @click="run(action)"
            >
                {{ action.label }}
            </DropdownMenuItem>
        </DropdownMenu>
    </template>
</TableBuilder>
```

`run` keeps the guard of [While an Action Runs](#while-an-action-runs): it does nothing while another row action of
the table runs. The slot gets no busy state, so a custom rendering shows none.

The resolved actions are also in `table.rowActions`, an array with one list of actions per row.
