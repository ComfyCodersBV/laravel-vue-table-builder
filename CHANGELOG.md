# Changelog

All notable changes to `laravel-vue-table-builder` will be documented in this file.

## 1.11.1 - 2026-10-26
* Updates the development dependencies in the lockfile for security advisories: undici 7.30.1 (denial of service via
  excessive content-encodings) and vite 8.3.5 (launch-editor path checks on Windows). The published package is unchanged.

## 1.11.0 - 2026-10-24
* Holding Shift while clicking a row checkbox selects or unselects every row between the last clicked row and this
  one, like Gmail: the range gets the new state of the clicked row. Only rows of the current page are in a range; when
  the last clicked row is not on it, only the clicked row changes. Shift-clicking no longer selects the text of the
  table. Keyboard use and tables without bulk actions are unchanged.
* A row action with an `icon` (from `rowAction(icon: ...)` in laravel-table-builder) shows that icon before its label,
  `aria-hidden`, so the label still names the button. The page maps names to components with
  `registerRowActionIcons({power: Power})` or the new `rowActionIcons` prop, which wins; an unknown name shows the label
  only. `resolveRowActionIcon()` is exported for custom `#actions` slots. Actions without an icon render as before.
* `SUPPORTED_SCHEMA_VERSION` is now `1.1`, the version laravel-table-builder emits with icons.

## 1.10.0 - 2026-10-20
* A row action's `confirm` can be a closure that gets the row, so the question can name it:
  `confirm: fn (App $app) => "Disconnect {$app->name}?"`. The core resolves it per row and the component asks the
  text of the row that was clicked. A row without a text runs without asking. Requires
  `tranquil-tools/laravel-table-builder` 1.1, which this package now requires.

## 1.9.2 - 2026-10-14
* Test hooks for browser recordings, next to the existing `data-filter-*` attributes: the filter toggle is
  `data-testid="table-filter"`, each text and select filter `table-filter-<key>`, plus `table-search`, `table-columns`,
  `table-row` on every data row and `table-empty` on the empty state. The pagination carries `table-count`,
  `table-previous`, `table-next` and `table-per-page`; the compact pagination above the table uses the `table-top-`
  prefix so both stay unique.

## 1.9.1 - 2026-10-14
* The check and cross of a boolean column are named with the translated `export.yes` and `export.no` (`Yes`/`No`,
  `Ja`/`Nee`) instead of `true` and `false`, so screen readers read a word instead of a boolean.

## 1.9.0 - 2026-10-13
* The PHP side moved to the new package `tranquil-tools/laravel-table-builder`, which this package requires, so
  `composer require tranquil-tools/laravel-vue-table-builder` installs both. The namespace `TranquilTools\TableBuilder\`
  is unchanged. This package keeps the Vue component and a small provider that shares the translations with Inertia.
* Configuration now lives in `config/table-builder.php` and translations under `table-builder::`. A published
  `config/vue-table-builder.php` or `lang/vendor/vue-table-builder` keeps working, and the old config key and
  translation namespace keep resolving. The publish tags are now `table-builder-config` and
  `table-builder-translations`.
* The component checks the `schemaVersion` of the payload against `SUPPORTED_SCHEMA_VERSION`: another major throws in
  development and warns in production, a payload without a version warns once in development.
* The bulk-action and reorder endpoints decide between JSON and a redirect on `expectsJson()` alone. An Inertia visit
  asks for HTML, so it still gets the redirect.

* A bulk action can ask for a choice: `bulkAction(choices: [...], choicesLabel: '...')`. The component opens a dialog
  with a select and posts the value as `choice`. The endpoint checks it against the choices of the table it builds
  again, answers `422` on a missing or unknown choice, and passes it to `before`, `each` and `after` as an extra
  argument. Actions without choices call their callbacks exactly as before. `AbstractTable::bulkActionChoice()` gives
  an overridden `performBulkAction()` the validated choice.
* The JSON of a bulk action no longer carries its `beforeCallback`, `eachCallback` and `afterCallback` keys. They were
  always empty objects, and Inertia resolves closures it finds in serialized props.
* `$table->poll(int $seconds)` reloads the rows on an interval, over Inertia and over http, keeping sorting, search,
  the page and the selection. It pauses while rows are selected, a dialog is open, a request runs or the tab is
  hidden, and catches up when the tab is visible again. `poll` is only in the payload when set.
* `$table->rowClass(callable)` sends a class per row as `__class`, merged with the `rowClass` prop, the prop last. A
  row that already has a `__class` value throws a `LogicException`. Tables without it send nothing extra.
* `useTranslations` is exported from the package entry. Its `table.` prefix stripping only applies to the table
  builder's own bundle; other bundles can pass `{group}` to strip the file name they were shared from.

## 1.8.1 - 2026-10-12
* The documented return type of `AbstractTable::for()` includes the Spatie `QueryBuilder` and a `DB::table()` query. A
  table class declaring `public function for(): QueryBuilder` no longer gets a `method.childReturnType` error from
  PHPStan. Spatie stays optional, because the type is only in the docblock. A table class fixture with such return
  types is analysed in CI.
* Explicitly checked ids on a table built on a Spatie `QueryBuilder` are no longer narrowed by the filters of the page
  query, as the docs already promised and as on other query tables: the endpoint builds `for()` again without the
  filter parameters. `ids: ['*']` still follows the posted state.
* Each row carries its raw row key as `__key`, taken before the cells are escaped or formatted, and the component reads
  the selection, the bulk action `ids`, the reorder ids and the `key` of `row-click` from it. A key containing HTML
  characters, such as `A&B`, was posted escaped when a column shared the key.

## 1.8.0 - 2026-10-12
* Rows are identified by a row key instead of `row.id`. The payload carries `rowKey`: the model's `getKeyName()` by
  default, so a model keyed by `uuid` works without configuration, or `id` for plain query and array tables.
  `rowKey('code')` sets another column; `primaryKey()` remains as the older name. Selection, the bulk action `ids`,
  reordering and the new `key` in the `row-click` payload use it. The endpoint matches posted ids on that column when it
  is not the primary key, and chunks a `DB::table()` query by it.
* The selection holds row keys instead of row positions. A selected row stays selected when the rows come back in
  another order, and the selection is kept while paging, sorting or changing the page size, so rows of several pages are
  posted together. Changing a filter or the search clears it, including "Select all N results". The header checkbox is
  checked when every row on the page is selected, and its menu offers "Select this page" whenever that is not the case.
  A row whose key is `0` is posted now instead of being dropped; a row without a key value cannot be selected.
* Tables built on a Spatie `QueryBuilder` honour the posted `state` of a bulk action over all results: the endpoint puts
  the sort and filters on the request and builds the table again, so `allowedFilters()` and `allowedSorts()` read them.
  `filteredQuery($request)` on such a table class builds `for()` again with that request.
* `AbstractTable::refresh()` forgets the built table and builds it again from the current request.

## 1.7.0 - 2026-10-12
* "Select all N results" now does what the docs promised: the component posts `ids: ['*']` instead of the ids on the
  current page, together with `state`, the sort, search and filters the table is showing as a query string. The bulk
  action endpoint takes the table's own sort and filter keys from `state` and runs the action over the table's
  filtered query, so it reaches every matching row on every page. Without `state` the filters of the signed page query
  apply, as before. The selection bar shows the total while all results are selected.
* A bulk action reads its rows in chunks of 1,000 by primary key without the table's sort. A table sorted on another
  column, or with a default sort, skipped rows past the first chunk; it now reaches all of them. Running a bulk action
  no longer adds the sort and eager loads to the table's own query either.
* The `confirm` option of a bulk action is honoured by the Vue component: it asks before posting, through
  `window.confirm()` with `confirmText` below the message, or through the component's `confirm` prop, which now gets
  a second `details` argument with `text`, `confirmButton` and `cancelButton` for bulk actions. `confirm: true` uses
  the new `bulk_actions.confirm` translation. Cancelling posts nothing and keeps the selection.
* `withoutColumnToggle()` and `columnToggle(false)` hide the "Columns" menu for a small or fixed table. The payload
  carries `columnToggle`, which is also `false` when no column can be hidden.
* `BulkAction` and `ConfirmDetails` are exported from the package entry.

## 1.6.1 - 2026-10-12
* A bulk action posted by the http transport gets `{"performed": true}` back from the package route instead of a
  redirect to the page, the same way reordering already answered. The `fetch()` no longer follows the redirect and
  renders the whole page only to throw it away, and success no longer depends on how that page responds. After a
  successful action the selection is cleared and the rows are refetched; after a failed one the selection stays and the
  first validation error or the `message` of the response is shown instead of the generic error.
* The filter toggle in the toolbar has an accessible name, translated through the new `filters.toggle` key.
* Tests cover the bulk action flow of both transports: the selection is cleared and the reloaded rows render after
  success, and the selection and the error stay after a failure.

## 1.6.0 - 2026-10-11
* `total('amount')` adds a footer row with a total per column, computed on the server over every row that matches the
  search and filters, not only the current page. `using` takes `sum`, `count`, `avg`, `min`, `max` or a callback
  receiving the filtered query; `pageOnly: true` totals the current page; `format` turns the value into text and
  `totalsLabel()` labels the row. The component renders a `<tfoot>` with a `total-{key}` slot.
* `exportable()` adds an "Export" button that streams a CSV of the current filtered, searched and sorted rows across all
  pages: UTF-8 with a BOM, read in chunks with `lazy()`, delimiter and decimal separator from the app locale or the
  config, and text cells starting with `=`, `+`, `-`, `@`, a tab or a carriage return prefixed with `'` so a
  spreadsheet never runs them. Columns take `exportAs:` and `export: false`; `columns:` picks, orders and renames them.
  The download runs through a signed package route and `authorizeExport()`; `url:` and `$table->export($request)` serve
  it from a route of your own.
* `filteredQuery(?Request)` on a query table and on `AbstractTable` returns the table's query with the search, filters
  and sort of the request applied and without pagination, for custom exports and reports.
* `rowAction(..., download: true)` renders a plain link with a `download` attribute instead of an Inertia visit, for
  PDFs and other files; a string sets the file name. A confirmed download starts after the question.
* `dateFilter()` and `dateRangeFilter()` take a `timezone` (or the `display_timezone` config): the picked days are read
  in that timezone and compared with the stored datetimes in the application timezone. `dateRangeFilter()` takes an
  `endColumn` to match records whose period overlaps the range (a `null` end is open-ended), and `presets` with a
  `defaultPreset`: built-in `today`, `upcoming`, `past`, `this_week`, `last_week`, `next_week`, `this_month`,
  `last_month`, `next_month` and `this_year`, or callbacks returning `[$from, $until)`. The component shows them in a
  dropdown next to a custom range, and "Any time" clears a default preset with an empty `..` range.
* Reordering numbers rows that share a position in the order the table shows them: the order column first, then the
  other `orderBy` clauses of the `for()` query, then `defaultSort()`, then the key. Ties used to be broken by the key,
  so a dragged row could land next to another neighbour than the one it was dropped on. The first move numbers every
  row from 1.
* Signed package urls (bulk actions, reordering, export) expire after `routes.signature_ttl` minutes (720 by default,
  `null` for no expiry) and carry the table's `signedParameters()`, which `fromSignedParameters()` uses to rebuild a
  table with constructor arguments. Before, the routes created the table with `app()`, which silently built tables bound
  to a record with an empty model. `routes.middleware` runs the routes in a middleware group of your own, such as
  `['web', 'auth']`, so the authorization hooks see the logged-in user. `authorizeBulkAction()`, `authorizeReorder()`
  and `authorizeExport()` fall back to `authorize()`. A refused request now answers `403` instead of a 500 from an
  `UnauthorizedException`, the bulk action route only accepts `AbstractTable` subclasses, and an unknown bulk action or
  a table that is not reorderable answers `404`.
* Icons are imported from `@lucide/vue`, the maintained successor of `lucide-vue-next` that Laravel's starter kits ship.
* Fix the phpstan config, which pointed at a `database` directory that does not exist, and make the codebase phpstan
  and Pint clean. CI runs Pint, phpstan and `vue-tsc` next to the tests.
* Docs: new pages for reordering, totals, export and the security of the package routes.

### Upgrade notes
* Install `@lucide/vue` (`npm install @lucide/vue`), or alias it to `lucide-vue-next` in Vite if you cannot.
* Signed urls rendered before the upgrade carry no expiry and are still accepted until the page reloads. Set
  `routes.signature_ttl` to `null` to keep urls that never expire.
* Run the package routes in your `web` stack with `auth` and authorize your tables, see `docs/security.md`. The default
  middleware stays empty so existing applications keep working.

## 1.5.0 - 2026-10-11
* `rowAction()` defines the buttons of every row in the Table class: a label, a url (string or
  callback) or route name, a method (`get`, `post`, `put`, `patch`, `delete`), an optional
  confirmation and a button variant. `visible` leaves an action out for a row, so policy checks
  never reach the browser, and `disabled` takes a reason that becomes the tooltip. The component
  renders them without an `actions` slot; the slot still wins and now receives the resolved
  `actions` and a `run` function. A `confirm` prop replaces `window.confirm()`.
* `emptyState()` and an `emptyState` prop set the title and description of an empty table, with
  separate texts while a search or filter is active. An `empty` slot receives `filtered`, the texts
  and a `reset` function that clears the search and filters.
* `reorderable('sort_order')` persists a drag-and-drop order on the server. The component posts the
  dragged row and its target to a signed package route, which moves the row within the table's query
  and renumbers the column, rewriting only the rows that changed. Pass `url:` to use a route of your
  own with `performReorder()` and the `ReorderRequest`, or call the `ReorderRows` action directly.
  The handles hide while the table is sorted on another column.
* `dateFilter()` and `dateRangeFilter()` filter a date column with `whereDate`, from and to
  inclusive and either end optional. A range travels as `from..to`.
* `filterQueryKey()` sets the query parameter of filters and search, and `queryPrefix()` replaces
  the table name prefix of every parameter. The resolved keys are sent as `queryKeys` and the
  component uses them. Without either, the parameters stay as they were.
* A filter given an array in the query string is ignored instead of throwing a `TypeError`.
* Fix three type errors when the Vue sources are type-checked with `vue-tsc`.

## 1.4.0 - 2026-10-01
* `reorderable` adds a leading drag handle to every row. Dropping a row on another emits
  `reorder` with `{from, to, row}`; the indexes are positions within the rows currently shown, and
  the component does not move anything itself, so the owner persists the new order and reloads.
* `rowClass` takes a `(row, index)` callback whose result is added to the row's classes, for
  status colours and similar.
* A `row-after` scoped slot renders extra rows under each row, receiving `row`, `index`, `columns`
  (the visible columns) and `colspan` (the full row width including the handle, selection and
  actions columns). `TableRow` and `TableCell` are exported so those rows match the table.
* A row without a row link emits `row-click` with `{row, index, event}` and gets the pointer cursor
  when a listener is bound, so an http table can open a record without a server-side row link.
  Clicks on the handle, selection and actions cells do not reach it, and neither do clicks in a
  column marked `clickable: false`.
* Fix `clickable: false` columns on tables with bulk actions. The clicked cell was matched to a
  column by its position, so the selection checkbox shifted every column by one and the wrong cell
  ignored the row link.

## 1.3.1 - 2026-09-17
* Allow @vueuse/core 15 in the peer range

## 1.3.0 - 2026-09-17
* `TableBuilder` takes a `transport` prop. The default `inertia` behaves exactly as before. With
  `transport="http"` and a `source` url the component fetches its own rows over `fetch()` and keeps
  sort, filter, search, per-page and page state in memory, so a table can be driven by an API that
  has nothing to do with the current Inertia page. Override the request with `fetcher` and the
  response shape with `adapter`. The composable is exported as `useTableTransport` for custom
  components.
* Pagination moved into its own `TablePagination` component and takes a `paginationPosition` prop
  (`top`, `bottom` or `both`). The previous/next buttons are now `disabled` instead of swapped for
  a look-alike `span`.
* Cells take a `cell-{key}` scoped slot receiving `row`, `value`, `index` and `column`. An `actions`
  slot adds a trailing column per row, and a `toolbar` slot adds controls next to the column
  selector.
* `column(..., boolean: true)` renders a check or cross icon instead of the raw value.
* `textFilter()` renders a debounced text input instead of a dropdown. It matches with a `LIKE`
  wildcard and ignores case; `selectFilter()` and `callbackFilter()` keep their exact,
  case-sensitive matching.
* Bulk actions posted over the http transport send the `X-XSRF-TOKEN` header and same-origin
  credentials. Without them Laravel rejected every bulk action on a web route with a `419`.
* A response that a newer request has overtaken is discarded instead of overwriting fresher rows,
  and changing `source` or `transport` after mount reloads the table.
* The Vue test suite runs on GitLab CI next to Pest.

## 1.2.3 - 2026-09-02
* Fix the header of the column named by `defaultSort` doing nothing when clicked. The column arrives sorted in the
  default direction, so the next step in the cycle cleared the `sort` parameter that was never in the URL, and the
  server re-applied the same default. That column now toggles between ascending and descending.

## 1.2.2 - 2026-08-24
* Update rowlink newtab call from `->rowLink($callback, href: true)->rowLinkTarget('_blank')` to `->rowLink($callback, href: true, newTab: true)`.

## 1.2.1 - 2026-08-24
* `rowLinkTarget('_blank')` opens a row link in a new tab. Without it rows still navigate in the
  current tab, so existing tables are unaffected. Combines with every row link type; the payload
  carries the target as `rowLinkTarget`.

## 1.2.0 - 2026-08-14

Released together with form-builder 1.2.0 and crud-builder 1.2.0.

* Drop the undeclared `lodash-es` import from `TableBuilder.vue`; search debouncing uses
  `useDebounceFn` from `@vueuse/core`, which consumers already have. The undeclared import broke
  `npm run build` in projects that had not installed `lodash-es` by chance.
* `package.json` declares the real import closure as `peerDependencies` with supported ranges
  (`reka-ui: ^2.9.4` among them). The old `dependencies` block resolved nothing and listed
  `laravel-vue-i18n`, which no source file imports.
* The manifest, lockfile, CI config and frontend tooling configs are `export-ignore`d, so dist
  installs carry only `resources/`, `src/`, `config/` and `routes/`.

## 1.1.1 - 2026-07-30
* **Breaking (security):** the bulk action route now validates its signature through Laravel's `ValidateSignature` middleware. Requests with a missing, tampered or expired signature are rejected with a `403` instead of being executed. Bulk action URLs generated by earlier versions were accepted unsigned.
* **Breaking:** the `columns[]` query parameter is applied server-side again. Hideable columns that are not listed in it now come back as `hidden`, instead of the parameter being ignored.
* `TableBuilder` no longer shadows `HasColumns::column()` and `HasColumns::columns()`. `classes:`, `highlight:`, `searchable:`, a `Closure` for `sortable:`, deriving a `key` from the `label`, replacing a column by re-registering its key, `defaultColumnCanBeHidden()` and `defaultHighlightFirstColumn()` all work now. Only `key` and `label` remain safe to pass positionally.
* Fix double filtering of unpaginated query builder results: the fetched rows were run through the in-memory collection filter again with the unsplit search term, so multi-word searches returned nothing. Array and `Collection` resources keep their in-memory filtering.
* Fix conditional class arrays on a column: `['w-16' => true, 'hidden' => false]` produced `"1 "` instead of `w-16`. Nested plain arrays still flatten.
* Accept a custom password field name in `bulkAction(requirePassword: 'current_password')`; the argument was typed `bool` and raised a `TypeError`. Note that the bundled Vue component does not prompt for a password and the package does not verify one.
* Serialize `allPerPageOptions()` as a JSON array again instead of an object with preserved keys.

## 1.1.0 - 2026-07-30
* **Breaking:** string values of columns without an `as:` closure are now escaped before they are sent to the frontend. Every cell is rendered with `v-html`, so raw model data previously reached the DOM unescaped. Columns that relied on that implicit HTML must now return an `Illuminate\Support\HtmlString` from an `as:` closure. Non-string values (int, bool, `null`, array) are unaffected.
* Add a Pest test suite covering the builder, query builder, concerns, components, the `make:table` command and the bulk action endpoint
* Run the test suite on GitLab CI for merge requests and pushes to `main`
* Document the escaping rule and correct stale documentation on columns, search, sorting, pagination, global defaults and bulk action security

## 1.0.12 - 2026-07-15
* Auto-derive per-table name from the configurator class and namespace page/perPage/filter params so multiple tables work without manual names

## 1.0.11 - 2026-07-15
* Automatically namespace pagination and query parameters per table by deriving the table `name` from the configurator class name (e.g. `LatestErrorsTable` → `latest_errors`). Multiple tables on one page no longer share the same `perPage`/`page`/search parameters. An explicit `name()` call still overrides the derived name.

## 1.0.10 - 2026-06-17
* Add `only` prop + client-side URL computation for prev/next to enable Inertia partial reloads per table while preserving other tables' page params in the URL

## 1.0.9 - 2026-06-15
* Add `href: true` parameter to `rowLink()` for plain browser navigation
* Fixed opening `rowModal`

## 1.0.8 - 2026-06-15
* `as:` callback return values are now escaped as plain text by default. Return an `HtmlString` instance to render raw HTML.

## 1.0.7 - 2026-06-03
* Add configurable table size dropdown in the pagination footer

## 1.0.6 - 2026-06-02
* Added pagination reset after filter changes

## 1.0.5 - 2026-05-27
* Drop return type 'mixed' from `AbstractTable::for()` to allow for more specific return types in implementations without causing type errors

## 1.0.4 - 2026-05-27
* Add documentation
* Add return types to `AbstractTable::for()`

## 1.0.3 - 2026-05-26
* Internalize all UI components to drop shadcn/ui requirement
* Add `button`, `input`, `checkbox`, `dropdown-menu` components under `resources/js/components/ui/`
* Add `useModal` composable
* Update all `@/` imports to relative paths

## 1.0.2 - 2026-05-12
* Add `->class(cell: '', head: '')` method to `TableBuilder` for global cell and header class overrides

## 1.0.1 - 2026-04-17
* Add Laravel 13 support

## 1.0.0 - 2026-04-08
* Cleanup and refactor for 1.0 release
* Added translations for global search, pagination and bulk actions

## 0.9 - 2026-04-07
* Add bulk action support with checkbox selection and action dropdown

## 0.8 - 2026-04-03
* Show which column is currently being sorted in which direction

## 0.7 - 2026-04-03
* Base sorting on table name when not using default name to allow multiple tables on one page

## 0.6 - 2026-03-25
* Apply defaultSort to query when no sort param is present

## 0.5 - 2026-03-20
* Use `fetch()` + `openModal` for row modal links instead of Inertia `router.visit()`

## 0.4 - 2026-03-20
* Add `callbackFilter()` method to support custom filter logic via a callable callback
* Support callable callbacks on `Filter` component, applied in `QueryBuilder` instead of default constraint
* Fix filter dropdown: correct option value/label binding, active value binding, and close dropdown on selection
* Fix `TableBuilder::toArray()` to use `filters()` (with request values applied) instead of raw `$this->filters`

## 0.3 - 2026-03-18
* Add clickable-parameter to table columns
* Pass original Eloquent model instances to TableBuilder column as() callbacks
* Make ->withGlobalSearch() unrequired & add/fix functionality for global search

## 0.2 - 2026-01-09
* Implemented search functionality and column toggling. Fixed tablebuilder for usage
* Made (normal) rowlink work
* Add support for as function and actions

## 0.1 - 2025-12-10
* Initial release
