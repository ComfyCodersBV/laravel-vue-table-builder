<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use TranquilTools\TableBuilder\Components\DateRangePreset;
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
        ?string $timezone = null,
    ): self {
        return $this->pushFilter(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: [],
            value: $defaultValue,
            noFilterOption: false,
            noFilterOptionLabel: '-',
            type: 'date',
            column: $column,
            timezone: $timezone ?? $this->defaultDisplayTimezone(),
        ));
    }

    /**
     * @param  array<int|string, string|Closure|DateRangePreset>  $presets
     */
    public function dateRangeFilter(
        string $key,
        ?string $label = null,
        ?string $defaultFrom = null,
        ?string $defaultTo = null,
        ?string $column = null,
        ?string $endColumn = null,
        ?string $timezone = null,
        array $presets = [],
        ?string $defaultPreset = null,
    ): self {
        $presets = DateRangePreset::normalize($presets);

        if (! is_null($defaultPreset) && ! array_key_exists($defaultPreset, $presets)) {
            throw new InvalidArgumentException("The default preset [{$defaultPreset}] is not one of the presets of the [{$key}] filter.");
        }

        return $this->pushFilter(new Filter(
            key: $key,
            label: $label ?: Str::headline($key),
            options: [],
            value: $defaultPreset ?? $this->defaultDateRange($defaultFrom, $defaultTo),
            noFilterOption: false,
            noFilterOptionLabel: '-',
            type: 'date_range',
            column: $column,
            endColumn: $endColumn,
            timezone: $timezone ?? $this->defaultDisplayTimezone(),
            presets: $presets,
        ));
    }

    private function defaultDateRange(?string $from, ?string $to): ?string
    {
        if (is_null($from) && is_null($to)) {
            return null;
        }

        return "{$from}..{$to}";
    }

    private function defaultDisplayTimezone(): ?string
    {
        $timezone = config('vue-table-builder.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : null;
    }

    private function pushFilter(Filter $filter): self
    {
        $this->filters = $this->filters
            ->reject(fn (Filter $existing) => $existing->key === $filter->key)
            ->push($filter)
            ->values();

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
