<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

final class Total
{
    public const AGGREGATES = [
        'sum',
        'count',
        'avg',
        'min',
        'max',
    ];

    public function __construct(
        public readonly string $key,
        public readonly string|Closure $using = 'sum',
        public readonly ?Closure $format = null,
        public readonly ?string $column = null,
        public readonly bool $pageOnly = false,
    ) {
        if (is_string($using) && ! in_array($using, self::AGGREGATES, true)) {
            throw new InvalidArgumentException("Total [{$key}] must use one of: ".implode(', ', self::AGGREGATES).', or a callback.');
        }
    }

    public function column(): string
    {
        return $this->column ?? $this->key;
    }

    public function computeOnQuery(BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder $query): mixed
    {
        $query = (clone $query)->reorder();

        if ($this->using instanceof Closure) {
            return call_user_func($this->using, $query);
        }

        if ($this->using === 'count') {
            return $query->count();
        }

        $column = $this->column();

        if (! str_contains($column, '.') && ! $query instanceof BaseQueryBuilder) {
            $column = $query->qualifyColumn($column);
        }

        return $query->{$this->using}($column);
    }

    public function computeOnItems(Collection $items): mixed
    {
        if ($this->using instanceof Closure) {
            return call_user_func($this->using, $items);
        }

        if ($this->using === 'count') {
            return $items->count();
        }

        return $items
            ->map(fn (mixed $item): mixed => data_get($item, $this->column()))
            ->filter(fn (mixed $value): bool => ! is_null($value))
            ->{$this->using}();
    }

    public function present(mixed $value): mixed
    {
        if (! is_null($this->format)) {
            $value = call_user_func($this->format, $value);
        }

        if ($value instanceof HtmlString) {
            return $value->toHtml();
        }

        if (is_string($value)) {
            return e($value);
        }

        return $value;
    }
}
