<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Components\Column;

trait HasColumns
{
    protected Collection $columns;

    protected static bool $defaultHighlightFirstColumn = false;

    protected static bool $defaultColumnCanBeHidden = true;

    public static function defaultColumnCanBeHidden(bool $state = true)
    {
        static::$defaultColumnCanBeHidden = $state;
    }

    public static function defaultHighlightFirstColumn(bool $state = true)
    {
        static::$defaultHighlightFirstColumn = $state;
    }

    public function column(
        ?string $key = null,
        ?string $label = null,
        ?bool $canBeHidden = null,
        bool $hidden = false,
        bool|Closure $sortable = false,
        bool|string $searchable = false,
        ?bool $highlight = null,
        array|string|null $classes = null,
        ?callable $as = null,
        string $alignment = 'left',
        bool $clickable = true,
    ): self
    {
        $key = $key !== null ? $key : Str::kebab($label);
        $label = $label !== null ? $label : Str::headline(str_replace('.', ' ', $key));

        $highlight = is_bool($highlight)
            ? $highlight
            : ($this->columns->isEmpty() ? static::$defaultHighlightFirstColumn : false);

        $canBeHidden = is_bool($canBeHidden)
            ? $canBeHidden
            : static::$defaultColumnCanBeHidden;

        $this->columns = $this->columns->reject(function (Column $column) use ($key) {
            return $column->key === $key;
        })->push(new Column(
            key: $key,
            label: $label,
            canBeHidden: $canBeHidden,
            hidden: $hidden,
            sortable: $sortable,
            sorted: false,
            highlight: $highlight,
            classes: $classes,
            as: $as,
            alignment: $alignment,
            clickable: $clickable,
        ))->values();

        if (! $searchable) {
            return $this;
        }

        return $this->searchInput(
            key: $key,
            label: $label,
        );
    }

    public function columns(): Collection
    {
        return $this->columns->map(function (Column $column) {
            $cloned = $column->clone();

            $sort = $this->query('sort', $this->defaultSort);

            $sorted = false;

            if ($sort === $column->key) {
                $sorted = 'asc';
            } else if ($sort === "-{$column->key}") {
                $sorted = 'desc';
            }

            $cloned->sorted = $sorted;

            $queryColumns = $this->query('columns', []);

            if (! empty($queryColumns) && $column->canBeHidden) {
                $cloned->hidden = ! in_array($column->key, $queryColumns);
            }

            return $cloned;
        });
    }

    public function defaultVisibleToggleableColumns(): array
    {
        return $this->columns
            ->filter(fn(Column $column) => ! $column->canBeHidden || ($column->canBeHidden && ! $column->hidden))
            ->map->key
            ->sort()
            ->values()
            ->all();
    }

    public function hasToggleableColumns(): bool
    {
        return $this->columns
            ->filter(fn(Column $column) => $column->canBeHidden)
            ->isNotEmpty();
    }
}
