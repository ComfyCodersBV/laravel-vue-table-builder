# Changelog

All notable changes to `laravel-vue-table-builder` will be documented in this file.

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
