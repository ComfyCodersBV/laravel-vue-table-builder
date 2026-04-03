# Changelog

All notable changes to `laravel-vue-table-builder` will be documented in this file.

## 1.0.0
* Cleanup and refactor for 1.0 release

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
