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
    ): self
    {
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

    public function callbackFilter(
        string $key,
        array $options,
        callable $callback,
        ?string $label = null,
        ?string $defaultValue = null,
        bool $noFilterOption = true,
        ?string $noFilterOptionLabel = null
    ): self
    {
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

    public function filters(): Collection
    {
        $queryFilters = $this->query('filter', []);

        $filters = $this->filters->map->clone()->keyBy->key;

        if (! empty($filters)) {
            $filters->each(function (Filter $filter) use ($queryFilters) {
                if (array_key_exists($filter->key, $queryFilters)) {
                    $filter->value = $queryFilters[$filter->key];
                }
            });
        }

        return $filters;
    }

    public function hasFilters(): bool
    {
        return $this->filters->isNotEmpty();
    }

    public function hasFiltersEnabled(): bool
    {
        return $this->filters()->reject(fn(Filter $filter) => is_null($filter->value))->isNotEmpty();
    }
}
