# Bulk Actions

Bulk actions let users select one or more rows and apply an action to all of them. Actions are processed server-side via
a signed POST route.

## Basic Usage

```php
$table->bulkAction(
    label: 'Delete',
    each: fn($user) => $user->delete(),
);
```

## Signature

```php
bulkAction(
    string $label,
    ?callable $each = null,
    ?callable $before = null,
    ?callable $after = null,
    bool|string $confirm = '',
    string $confirmText = '',
    string $confirmButton = '',
    string $cancelButton = '',
    bool|string $requirePassword = false,
    array $choices = [],
    string $choicesLabel = '',
): self
```

## Parameters

| Parameter         | Type             | Default | Description                                                              |
|-------------------|------------------|---------|--------------------------------------------------------------------------|
| `label`           | `string`         | -       | Button label shown in the UI                                             |
| `each`            | `callable\|null` | `null`  | Called once per selected row; receives the model instance                |
| `before`          | `callable\|null` | `null`  | Called once before processing starts; receives the array of IDs          |
| `after`           | `callable\|null` | `null`  | Called once after all rows are processed; receives the array of IDs      |
| `confirm`         | `bool\|string`   | `''`    | Show a confirmation dialog; pass `true` or a custom message string       |
| `confirmText`     | `string`         | `''`    | Body text inside the dialog                                              |
| `confirmButton`   | `string`         | `''`    | Confirm button label                                                     |
| `cancelButton`    | `string`         | `''`    | Cancel button label                                                      |
| `requirePassword` | `bool\|string`   | `false` | Password field name to send along; `true` becomes `password` (see below) |
| `choices`         | `array`          | `[]`    | Value => label options the user picks one from before posting (see below) |
| `choicesLabel`    | `string`         | `''`    | Label of the select in the choice dialog; falls back to `label`          |

## Callbacks

### `each` - per-row processing

```php
->bulkAction('Activate', each: fn($user) => $user->update(['active' => true]))
```

Rows are processed in chunks of 1,000 by primary key (`chunkById`), so a table sorted on another column still reaches
every row and memory stays flat.

### `before` and `after` - hooks

```php
->bulkAction(
    label: 'Export',
    before: fn($ids) => logger('Starting export for ' . count($ids) . ' users'),
    each: fn($user) => $user->export(),
    after: fn($ids) => logger('Export complete'),
)
```

## Confirmation Dialog

```php
->bulkAction(
    label: 'Delete',
    each: fn($user) => $user->delete(),
    confirm: 'Are you sure you want to delete the selected users?',
    confirmText: 'This action cannot be undone.',
    confirmButton: 'Yes, delete',
    cancelButton: 'Cancel',
)
```

Pass `true` to use the default translated confirmation message (`bulk_actions.confirm`).

The Vue component asks before it posts, through the same mechanism as [row actions](row-actions.md#how-they-run):
`window.confirm()` with the message and `confirmText` below it, or the component's `confirm` prop. That prop gets the
message and the other texts:

```vue
<TableBuilder
    :table="table"
    :confirm="(message, details) => dialog.ask(message, details?.text, details?.confirmButton, details?.cancelButton)"
/>
```

`details` is only passed for bulk actions; a row action calls the prop with the message alone, already resolved for
the clicked row when its `confirm` is a closure ([Confirmation Per Row](row-actions.md#confirmation-per-row)).
Cancelling keeps the selection and posts nothing.

## Choices

A bulk action can ask for one value before it runs, such as a folder, a status or a user:

```php
->bulkAction(
    label: 'Move to',
    before: fn (array $ids, string $folderId) => $move->handle($ids, $folderId),
    choices: $folders->pluck('name', 'id')->all(),
    choicesLabel: 'Folder',
    confirmButton: 'Move',
)
```

The Vue component opens a dialog with a select instead of the confirmation: its title is the `confirm` string or the
label, `confirmText` goes below it, and `confirmButton` and `cancelButton` label its buttons. The request carries the
picked value as `choice`, always as a string.

The endpoint builds the table again and checks `choice` against the keys of its `choices`, so a value the table does
not offer, or no value, answers `422` with an error on `choice`. Choices are never taken from the client.

The callbacks of an action with choices get the choice as an extra argument:

| Callback | Arguments                |
|----------|--------------------------|
| `before` | `array $ids, string $choice` |
| `each`   | `$row, string $choice`       |
| `after`  | `array $ids, string $choice` |

An action without choices calls its callbacks exactly as before: `before` and `after` with `$ids`, `each` through
`Collection::each()`. A posted `choice` is ignored for such an action.

The callbacks are stored as `Closure`, so pass closures or first-class callables such as `$this->move(...)`. A string
naming a built-in function, such as `'strtoupper'`, would not work anyway: it refuses the extra argument.

If you override `performBulkAction()`, read the validated choice with `$this->bulkActionChoice()`.

## Password Confirmation

Mark an action as requiring the user's password. `true` becomes the field name `password`; pass a string for a custom
field name:

```php
->bulkAction(
    label: 'Permanently Delete',
    each: fn($user) => $user->forceDelete(),
    requirePassword: true,
)

->bulkAction(
    label: 'Permanently Delete',
    each: fn($user) => $user->forceDelete(),
    requirePassword: 'current_password',
)
```

> **Warning:** this only travels along in the `requirePassword` key of the action payload. The bundled Vue component
> does not prompt for a password, and the package does not verify one server-side. Treat it as a hint for your own
> frontend, and check the password yourself in `authorize()` if you depend on it.

## Row Key

The component identifies rows by their row key: it is what a selection holds, what `ids` contains, what a reorder posts
and the `key` of `row-click`. The table sends it as `rowKey`. By default it is the model's `getKeyName()`, so a model
with `protected $primaryKey = 'uuid'` works without configuration; plain query and array tables use `id`.

Set it when rows are identified by another column:

```php
$table->rowKey('code');
```

The endpoint then matches posted ids on that column (`whereIn('code', $ids)`), and a plain `DB::table()` query is
chunked by it. The column has to be in the row data the table sends, and unique. Each row also carries the raw key
value as `__key`, taken before the cells are escaped or formatted, and the component reads that one: a key like `A&B`
is posted as `A&B`, even when a column on the same key shows it escaped or reformatted by its `as`. `primaryKey()` is
the older name for the same setting.

## Selecting Rows

In the frontend, users can:

- **Select individual rows** via the row checkbox
- **Select a range** by holding Shift while clicking a row checkbox: every row between the last clicked row and this
  one gets the new state of this row, so a Shift-click selects or unselects the whole range, like Gmail. The range only
  covers the rows of the current page; when the last clicked row is on another page, or there is none yet, only the
  clicked row changes. Keyboard use is unchanged
- **Select all on this page** via the header checkbox
- **Select all results** (across all pages) via the "Select all N results" prompt that appears after selecting the
  current page

The selection holds row keys, not positions, so a selected row stays selected when the rows come back in another
order. It is kept while the user pages, sorts or changes the page size, so rows from several pages can be selected
and are posted together. Changing a filter or the search clears the selection, including "all results": the rows the
user picked may no longer be in the list they are looking at. A row without a row key value cannot be selected.

When "all results" is selected, the selection bar shows the total and the component posts `ids: ['*']` together with
`state`: the sort, search and filters the table is showing, as a query string (`filter[status]=open&sort=-created_at`,
namespaced like every table parameter). The endpoint runs the action over the table's `filteredQuery()` with that
state, so it reaches exactly the rows matching what the user sees, across every page, in chunks. Only the table's own
sort and filter keys are taken from `state`; everything else in it is ignored.

Without `state` (an older frontend, or your own client posting `['*']`) the filters from the page query the bulk action
url was signed with are used, as before. Posted ids other than `['*']` are never narrowed by filters, on a Spatie
`QueryBuilder` table neither: the endpoint builds `for()` again without the filter parameters of the page query.

A table built on a Spatie `QueryBuilder` honours `state` too: the endpoint puts the posted sort and filters on the
request and builds the table again, so the `allowedFilters()` and `allowedSorts()` in `for()` read them. This needs
`for()` to build the Spatie query from the current request, which is what `SpatieQueryBuilder::for(User::class)` does
when you pass no request of your own.

If you override `performBulkAction()` on an `AbstractTable`, `$ids === ['*']` means "everything in
`$this->filteredQuery()`", which already carries the posted state:

```php
public function performBulkAction(int $key, array $ids): void
{
    $query = $ids === ['*'] ? $this->filteredQuery() : User::query()->whereKey($ids);

    $query->reorder()->chunkById(1000, fn ($users) => $users->each->archive());
}
```

## Security

Bulk action urls are signed by the server with an expiry and posted to the package route
`table.bulk-action`. The signature covers the table class, its signed parameters, the action key and
the query string of the page, so none of them can be swapped. The posted `ids` and `state` are not
part of it, but they only ever select rows within the table's own `for()` query: `state` can narrow
the rows with the table's own filters and search, never widen them, the same way the export does.

The signature does not say *who* is calling. Run the package routes in your `web` stack with `auth`
and authorize the action on the table:

```php
public function authorizeBulkAction(Request $request, int $key): bool
{
    return $request->user()->can('delete', User::class);
}
```

`authorizeBulkAction()` falls back to `authorize()`, which returns `true` by default. A refused
action answers `403`. See [Security](security.md) for the middleware, the url lifetime and tables
with constructor arguments.

## Handling in AbstractTable

When using `AbstractTable`, override `performBulkAction` for custom control:

```php
public function performBulkAction(int $key, array $ids): void
{
    match ($key) {
        0 => User::whereKey($ids)->update(['active' => true]),
        1 => User::whereKey($ids)->delete(),
        default => throw new \InvalidArgumentException("Unknown action: {$key}"),
    };
}
```

`$key` is the zero-based index of the action in the order they were registered in `configure()`.

## Multiple Bulk Actions

```php
$table
    ->bulkAction('Activate', each: fn($u) => $u->update(['active' => true]))
    ->bulkAction('Deactivate', each: fn($u) => $u->update(['active' => false]))
    ->bulkAction('Delete', each: fn($u) => $u->delete(), confirm: 'Delete selected?');
```
