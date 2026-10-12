# Security of the Package Routes

Bulk actions, reordering and the CSV export post to (or download from) routes the package
registers itself:

| Route                    | Name                | Used by                          |
|--------------------------|---------------------|----------------------------------|
| `POST /table/bulk-action` | `table.bulk-action` | [Bulk Actions](bulk-actions.md)  |
| `POST /table/reorder`     | `table.reorder`     | [Reordering](reordering.md)      |
| `GET /table/export`       | `table.export`      | [Export](export.md)              |

The component never builds these urls. The server signs them when it renders a table, so a url
always names the Table class, its parameters and (for bulk actions) the action it was made for.

## Recommended Setup

Publish the config and run the routes inside your `web` stack with a logged-in user:

```php
// config/vue-table-builder.php
'routes' => [
    'middleware' => ['web', 'auth'],
    'signature_ttl' => 720,
],
```

Then authorize every table that exposes one of these routes:

```php
public function authorize(Request $request): bool
{
    return $request->user()->can('viewAny', Room::class);
}

public function authorizeBulkAction(Request $request, int $key): bool
{
    return $request->user()->can('delete', Room::class);
}

public function authorizeReorder(Request $request): bool
{
    return $request->user()->can('update', $this->venue);
}
```

Without `web` the routes have no session, so `$request->user()` is `null` inside the hooks and
there is no CSRF check. The defaults keep the routes outside any group so existing applications keep
working after an upgrade; switch to the setup above when you can.

## What the Signature Covers

Each url is made with `URL::temporarySignedRoute()` and checked by Laravel's `ValidateSignature`
(the export route uses its own check, see below). A missing, changed or expired signature answers
`403` before any table code runs.

- `table` - the Table class. Only subclasses of `AbstractTable` are accepted; anything else is a `404`.
- `params` - the table's [signed parameters](#tables-with-constructor-arguments).
- `action` - the bulk action key. A key the table does not have is a `404`.
- `expires` - the expiry, see below.

A bulk action url also signs the query string of the page it was rendered on. For "select all
results" the component posts the sort, search and filters it is showing as `state` in the body, which
takes the place of the table's own sort and filter keys from that query. The export url signs only `table`, `params` and
`expires`: the component appends the current sort, search and filters when the button is pressed, so
those may change after rendering. They select rows within the same table and are checked by the same
authorization as the page itself.

What the signature does not cover is *who* is calling: it proves the url was rendered by this
application, not that the current user may use it. That is what the middleware and the authorization
hooks are for.

## Expiry

`routes.signature_ttl` is the lifetime of a signed url in minutes and defaults to `720` (12 hours).
Every Inertia visit renders fresh urls, so only a tab left open longer than that sees a `403`.
Set it to `null` or `0` to sign urls without an expiry, as versions before 1.6 did.

## Authorization Hooks

| Method                                             | Called by                | Default             |
|----------------------------------------------------|--------------------------|---------------------|
| `authorize(Request $request): bool`                | the hooks below          | `true`              |
| `authorizeBulkAction(Request $request, int $key)`  | the bulk action route    | `authorize()`       |
| `authorizeReorder(Request $request)`               | the reorder route        | `authorize()`       |
| `authorizeExport(Request $request)`                | the export route         | `authorize()`       |

Returning `false` answers `403` (an `AuthorizationException`). Rendering a table never calls these
hooks: guard the page itself in its controller. Gate the button too, so nobody sees an action they
cannot use: only register a bulk action, `reorderable()` or `exportable()` when the user may use it,
for example with `$table->when($user->can('export', Booking::class), fn ($table) => $table->exportable())`.
The routes answer `404` for a table that does not offer the feature.

## Tables with Constructor Arguments

The routes create the Table class again from the url. By default that is `app(TableClass::class)`,
which only works for tables without constructor arguments, or with arguments the container can
resolve. A table bound to a record tells the package what to sign and how to rebuild itself:

```php
class RoomTable extends AbstractTable
{
    public function __construct(private readonly Venue $venue) {}

    public function for(): Builder
    {
        return $this->venue->rooms()->getQuery();
    }

    public function signedParameters(): array
    {
        return [
            'venue' => $this->venue->id,
        ];
    }

    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return new static(Venue::query()->findOrFail($parameters['venue']));
    }
}
```

`signedParameters()` returns scalars only. They travel base64-encoded in the signed `params`
parameter, so the venue cannot be swapped for another one. The rebuilt table runs the same `for()`
query, which scopes bulk actions, reordering and exports to that venue's rooms.

Prefer a route of your own instead? Pass `url:` to `reorderable()` or `exportable()` and handle the
request in your controller, see [Reordering](reordering.md#using-your-own-route) and
[Export](export.md#custom-exports).
