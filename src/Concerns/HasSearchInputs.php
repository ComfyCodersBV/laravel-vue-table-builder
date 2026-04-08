<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Components\SearchInput;
use TranquilTools\TableBuilder\TableBuilder;

trait HasSearchInputs
{
    protected Collection $searchInputs;

    protected static bool|string $defaultGlobalSearch = false;

    private static function normalizeSearchColumnsWithMethod(array $keys): array
    {
        return Collection::make($keys)->mapWithKeys(function ($value, $key) {
            if (is_numeric($key)) {
                return [$value => null];
            }

            return [$key => $value];
        })->all();
    }

    public function searchInput(
        array|string $key,
        ?string $label = null,
        ?string $defaultValue = null,
        array $columns = []
    ): self
    {
        if (empty($columns)) {
            $columns = Arr::sort(Arr::wrap($key));
        }

        if (is_array($key)) {
            $key = Str::slug(implode(' ', $columns));
        }

        $columns = static::normalizeSearchColumnsWithMethod($columns);

        $this->searchInputs = $this->searchInputs->reject(function (SearchInput $searchInput) use ($key) {
            return $searchInput->key === $key;
        })->push(new SearchInput(
            key: $key,
            columns: $columns,
            label: $label ?: Str::headline($key),
            value: $defaultValue,
        ))->values();

        return $this;
    }

    public function searchInputs(?string $key = null): Collection|SearchInput|null
    {
        $filters = $this->query('filter', []);

        $searchInputs = $this->searchInputs->map->clone()->keyBy->key;

        if (! empty($filters)) {
            // Apply the input value from the request query.
            $searchInputs->each(function (SearchInput $searchInput) use ($filters) {
                if (array_key_exists($searchInput->key, $filters)) {
                    $searchInput->value = $filters[$searchInput->key];
                }
            });
        }

        return $key ? $searchInputs->get($key) : $searchInputs;
    }

    public function hasSearchFiltersEnabled(): bool
    {
        return $this->searchInputs()->filter->value->isNotEmpty();
    }

    public function hasToggleableSearchInputs(): bool
    {
        return $this->searchInputs
            ->reject(fn(SearchInput $searchInput) => $searchInput->key === TableBuilder::GLOBAL_SEARCH_KEY)
            ->isNotEmpty();
    }

    public static function defaultGlobalSearch(bool|string $label = 'Search')
    {
        static::$defaultGlobalSearch = $label !== false
            ? trans($label) . '...'
            : false;
    }

    public function withGlobalSearch(?string $label = null, array $columns = []): self
    {
        return $this->searchInput(
            key: TableBuilder::GLOBAL_SEARCH_KEY,
            label: $label ?: trans('vue-table-builder::table.search') . '...',
            columns: $columns
        );
    }

    public function withoutGlobalSearch(): self
    {
        $this->searchInputs = $this->searchInputs->reject(
            fn(SearchInput $searchInput) => $searchInput->key === TableBuilder::GLOBAL_SEARCH_KEY
        )->values();

        return $this;
    }
}
