<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Components\Filter;

trait HasFilters
{
    public function selectFilter(
        string $key,
        array $options,
        ?string $label = null,
        ?string $defaultValue = null,
        bool $noFilterOption = true,
        ?string $noFilterOptionLabel = null
    ): self {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: $options,
            value: $defaultValue,
            noFilterOption: $noFilterOption,
            noFilterOptionLabel: $noFilterOptionLabel ?: '-',
            type: 'select'
        ))->values();

        return $this;
    }

    public function textFilter(
        string $key,
        ?string $label = null,
        ?string $defaultValue = null
    ): self {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: [],
            value: $defaultValue,
            noFilterOption: false,
            noFilterOptionLabel: '-',
            type: 'text'
        ))->values();

        return $this;
    }

    public function callbackFilter(
        string $key,
        array $options,
        callable $callback,
        ?string $label = null,
        ?string $defaultValue = null,
        bool $noFilterOption = true,
        ?string $noFilterOptionLabel = null
    ): self {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: $options,
            value: $defaultValue,
            noFilterOption: $noFilterOption,
            noFilterOptionLabel: $noFilterOptionLabel ?: '-',
            type: 'select',
            callback: $callback,
        ))->values();

        return $this;
    }

    public function dateFilter(
        string $key,
        ?string $label = null,
        ?string $defaultValue = null,
        ?string $column = null,
    ): self {
        return $this->pushDateFilter('date', $key, $label, $defaultValue, $column);
    }

    public function dateRangeFilter(
        string $key,
        ?string $label = null,
        ?string $defaultFrom = null,
        ?string $defaultTo = null,
        ?string $column = null,
    ): self {
        $defaultValue = is_null($defaultFrom) && is_null($defaultTo)
            ? null
            : "{$defaultFrom}..{$defaultTo}";

        return $this->pushDateFilter('date_range', $key, $label, $defaultValue, $column);
    }

    private function pushDateFilter(string $type, string $key, ?string $label, ?string $defaultValue, ?string $column): self
    {
        $this->filters = $this->filters->reject(function (Filter $filter) use ($key) {
            return $filter->key === $key;
        })->push(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: [],
            value: $defaultValue,
            noFilterOption: false,
            noFilterOptionLabel: '-',
            type: $type,
            column: $column,
        ))->values();

        return $this;
    }

    public function filters(): Collection
    {
        $queryFilters = $this->query('filter', []);

        $queryFilters = is_array($queryFilters) ? $queryFilters : [];

        return $this->filters->map->clone()->keyBy->key->each(function (Filter $filter) use ($queryFilters) {
            if (array_key_exists($filter->key, $queryFilters) && ! is_array($queryFilters[$filter->key])) {
                $filter->value = $queryFilters[$filter->key];
            }
        });
    }

    public function hasFilters(): bool
    {
        return $this->filters->isNotEmpty();
    }

    public function hasFiltersEnabled(): bool
    {
        return $this->filters()->reject(fn (Filter $filter) => is_null($filter->value))->isNotEmpty();
    }
}
