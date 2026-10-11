<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use TranquilTools\TableBuilder\Components\Total;

trait HasTotals
{
    /**
     * @var array<string, Total>
     */
    protected array $totals = [];

    protected ?string $totalsLabel = null;

    public function total(
        string $key,
        string|Closure $using = 'sum',
        ?Closure $format = null,
        ?string $column = null,
        bool $pageOnly = false,
    ): self {
        $this->totals[$key] = new Total(
            key: $key,
            using: $using,
            format: $format,
            column: $column,
            pageOnly: $pageOnly,
        );

        return $this;
    }

    public function totalsLabel(?string $label): self
    {
        $this->totalsLabel = $label;

        return $this;
    }

    public function hasTotals(): bool
    {
        return $this->totals !== [];
    }

    /**
     * @return array<string, Total>
     */
    public function getTotals(): array
    {
        return $this->totals;
    }

    protected function totalsQuery(): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder|null
    {
        return null;
    }

    protected function allItemsForTotals(Collection $pageItems): Collection
    {
        return $pageItems;
    }

    /**
     * @return array{label: string|null, values: array<string, mixed>}|null
     */
    protected function resolveTotals(Collection $pageItems): ?array
    {
        if (! $this->hasTotals()) {
            return null;
        }

        $query = $this->totalsQuery();

        $values = [];

        foreach ($this->totals as $key => $total) {
            $values[$key] = $total->present($this->computeTotal($total, $query, $pageItems));
        }

        return [
            'label' => $this->totalsLabel,
            'values' => $values,
        ];
    }

    private function computeTotal(Total $total, BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder|null $query, Collection $pageItems): mixed
    {
        if ($total->pageOnly) {
            return $total->computeOnItems($pageItems);
        }

        if (! is_null($query)) {
            return $total->computeOnQuery($query);
        }

        return $total->computeOnItems($this->allItemsForTotals($pageItems));
    }
}
