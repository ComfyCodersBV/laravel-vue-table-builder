# CrudBuilder

The **[CrudBuilder](https://docs.comfycoders.nl/laravel-vue-crud-builder)** (`tranquil-tools/laravel-vue-crud-builder`)
scaffolds complete create, read, update and delete screens on top of this package and the
[FormBuilder](https://docs.comfycoders.nl/laravel-vue-form-builder). This page describes what it does with tables.
The index page it generates is an ordinary table class and `TableBuilder` component, so everything in these docs
applies to it.

## What it adds on top of TableBuilder

TableBuilder turns a table class into a sortable, searchable, paginated list. You still decide which columns to show,
write the controller action that builds the table and create the page that renders it. The CrudBuilder does that for
you, based on the model's database table:

```bash
php artisan make:crud Product --model=App\\Models\\Product
```

For the list side of a resource, it generates:

- **A table class with columns from your database schema.** `app/Tables/ProductTable.php` gets a column for every
  database column except `password` and `remember_token`, labelled with the headline of the column name. String,
  numeric, boolean and date columns are [sortable](sorting.md); text, JSON and other columns are not.
- **Global search on the obvious columns.** When the table has a `name`, `title`, `email`, `username` or `slug`
  column, the table enables [global search](search.md) on exactly those columns.
- **Pagination.** The query is [paginated](pagination.md) at 25 rows per page.
- **An index action with row links.** `index()` builds the table and adds a [row link](row-links.md) to the detail page
  of each record, plus a `createRoute` for the create button.
- **An index page.** `Products/Index.vue` renders the `TableBuilder` component with a title and a create button. The
  detail page `Products/Show.vue` shows the record's attributes, and the create/edit form comes from the FormBuilder.

## Example

A `products` table with `id`, `name`, `description`, `price` and the timestamps results in a table class like this:

```php
class ProductTable extends AbstractTable
{
    public function for(): Builder
    {
        return Product::query();
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->withGlobalSearch(columns: ['name'])
            ->column('id', 'ID', sortable: true)
            ->column('name', 'Name', sortable: true)
            ->column('description', 'Description')
            ->column('price', 'Price', sortable: true)
            ->column('created_at', 'Created At', sortable: true)
            ->column('updated_at', 'Updated At', sortable: true)
            ->paginate(25);
    }
}
```

The generated controller passes it to the index page:

```php
public function index(): Response
{
    return Inertia::render('Products/Index', [
        'table' => ProductTable::build()->rowLink(fn ($row) => route('products.show', $row)),
        'title' => 'Products',
        'createRoute' => route('products.create'),
    ]);
}
```

Without a model, or when the schema cannot be read, the command generates a table with only an `id` column on an empty
array, which you point at your own data source.

From there you extend the table like any other: add [filters](filters.md), [row actions](row-actions.md),
[bulk actions](bulk-actions.md), [totals](totals.md) or a [CSV export](export.md), and add an `authorize()` method to
restrict who may see it (see [Security](security.md)).

## When to use it

Use the CrudBuilder when a model needs the usual admin screens: a list, a detail page and a form to create and edit
records. It saves writing the same table, controller and pages for every resource, and the generated code is yours to
change.

Stay with TableBuilder alone for tables that are not a model's CRUD index: a report over an aggregate query, a table
fed by an array or an [external API](http-transport.md), or a table embedded in another page. `make:table` gives you
an empty table class for those.

## Installation

```bash
composer require tranquil-tools/laravel-vue-crud-builder
php artisan crud-builder:install
```

The CrudBuilder requires this package and the FormBuilder, so Composer installs both. When this package is already in
your project, nothing changes for your existing tables.

Read the [CrudBuilder documentation](https://docs.comfycoders.nl/laravel-vue-crud-builder) for every option of
`make:crud`, the full schema mapping and the shared Vue pages, or browse the source on
[GitHub](https://github.com/ComfyCodersBV/laravel-vue-crud-builder).
