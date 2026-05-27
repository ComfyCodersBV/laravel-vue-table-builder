# Table Classes

Table classes are the primary way to define a table. They extend `AbstractTable` and encapsulate the data source,
authorization, and column/filter configuration in one place.

## Creating a Table Class

```bash
php artisan make:table UsersTable
```

Or create manually in `app/Tables/`:

```php
<?php

namespace App\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

class UsersTable extends AbstractTable
{
    public function for(): Builder|array
    {
        return User::query();
    }

    public function authorize(Request $request): bool
    {
        return $request->user()->can('viewAny', User::class);
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('id', 'ID', canBeHidden: false)
            ->column('name', 'Name', sortable: true)
            ->column('email', 'Email', sortable: true)
            ->paginate(15);
    }
}
```

## AbstractTable Methods

### `for()`

Returns the data source. Can be an Eloquent query builder, the classname of the model, a model instance, a relation, a collection or an array.

#### Eloquent query builder:
```php
public function for(): Builder
{
    return User::query()->with('company');
}
```

#### Model class name (returns all records, can be filtered/sorted/searched like a query builder):
```php
public function for(): string
{
    return User::class;
}
```

#### A relation is unwrapped to its underlying query builder, so sorting, filtering and searching still work:

```php
public function for(): HasMany
{
    return $this->user->posts();
}
```

#### A model instance is converted to a query builder scoped to that model:

```php
public function for(): User
{
    return User::find($this->userId);
}
```

#### Plain arrays work too, though filtering and sorting happen in-memory:

```php
public function for(): array
{
    return [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];
}
```

#### Even complex data sources can be supported by returning a collection or array:
```php

    public function for(): Collection
    {
        $reactions = Reaction::query()
            ->where([
                // ...
            ])
            ->get()
            ->transform(function (Reaction $reaction) {
                // ...

                return $reaction;
            });

        $tickets = Ticket::query()
            ->where([
                // ...
            ])
            ->get();

        return $tickets
            ->merge($reactions)
            ->sortBy('created_at');
    }
```

When returning a query builder, the package wraps it in `QueryBuilder` which handles filtering, sorting, searching, and
pagination automatically.

### `authorize(Request $request)`

Return `true` to allow access, `false` to deny (returns 403). Defaults to `true`.

```php
public function authorize(Request $request): bool
{
    return $request->user()->isAdmin();
}
```

### `configure(TableBuilder $table)`

Define columns, filters, search inputs, bulk actions, and other options. See the individual feature pages for full
options.

### `build(...$arguments): TableBuilder`

Static factory that instantiates the table, calls `for()`, `authorize()`, and `configure()`, then returns the
ready-to-serialize `TableBuilder`.

Pass constructor arguments if your table class needs them:

```php
class OrdersTable extends AbstractTable
{
    public function __construct(private readonly Customer $customer) {}

    public function for(): HasMany
    {
        return $this->customer->orders();
    }
}

// In controller:
'table' => OrdersTable::build($customer),
```

### `make(): TableBuilder`

Same as `build()` but without calling `beforeRender()`. Useful when you need to inspect the builder before it executes
the query.

### `performBulkAction(int $key, array $ids)`

Override to handle bulk action execution. `$key` is the zero-based index of the action as registered in `configure()`.
`$ids` is the array of selected primary keys (or `['*']` for "all results").

```php
public function performBulkAction(int $key, array $ids): void
{
    match ($key) {
        0 => User::whereKey($ids)->update(['active' => true]),
        1 => User::whereKey($ids)->delete(),
    };
}
```

## Using in a Controller

```php
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

## Without a Table Class

You can build a table inline using `TableBuilder::for()`:

```php
use TranquilTools\TableBuilder\TableBuilder;

public function index()
{
    $table = TableBuilder::for(User::query())
        ->column('id', 'ID')
        ->column('name', 'Name', sortable: true)
        ->paginate(15);

    return Inertia::render('Users/Index', [
        'table' => $table,
    ]);
}
```

## Multiple Tables on One Page

Give each table a unique name to namespace its query parameters:

```php
$usersTable = TableBuilder::for(User::query())
    ->name('users')
    ->column('name', 'Name')
    ->paginate(15);

$rolesTable = TableBuilder::for(Role::query())
    ->name('roles')
    ->column('name', 'Name')
    ->paginate(10);

return Inertia::render('Admin/Dashboard', [
    'users' => $usersTable,
    'roles' => $rolesTable,
]);
```

Named tables prefix their query params: `users_sort`, `users_page`, `roles_sort`, etc.

In Vue, pass the name prop:

```vue
<TableBuilder :table="users" name="users" />
<TableBuilder :table="roles" name="roles" />
```
