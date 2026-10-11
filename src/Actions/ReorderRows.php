<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Actions;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReorderRows
{
    public function __invoke(
        EloquentBuilder|BaseQueryBuilder $query,
        string $column,
        string $keyName,
        int|string $id,
        int|string $toId,
    ): void {
        if ((string) $id === (string) $toId) {
            return;
        }

        $connection = $query instanceof EloquentBuilder
            ? $query->getModel()->getConnection()
            : $query->getConnection();

        $connection->transaction(function () use ($query, $column, $keyName, $id, $toId) {
            $positions = $this->currentPositions($query, $column, $keyName);

            $ids = array_map('strval', array_keys($positions));
            $from = array_search((string) $id, $ids, true);
            $to = array_search((string) $toId, $ids, true);

            if ($from === false || $to === false) {
                throw new InvalidArgumentException('Both rows must belong to the table that is reordered.');
            }

            array_splice($ids, $from, 1);
            array_splice($ids, $to, 0, [(string) $id]);

            foreach ($ids as $index => $rowId) {
                if ((string) $positions[$rowId] === (string) ($index + 1)) {
                    continue;
                }

                $this->freshQuery($query)
                    ->where($keyName, $rowId)
                    ->update([
                        $column => $index + 1,
                    ]);
            }
        });
    }

    /**
     * @return array<int|string, mixed>
     */
    private function currentPositions(EloquentBuilder|BaseQueryBuilder $query, string $column, string $keyName): array
    {
        $ordered = clone $query;

        $qualifiedColumn = $ordered instanceof EloquentBuilder ? $ordered->qualifyColumn($column) : $column;
        $qualifiedKey = $ordered instanceof EloquentBuilder ? $ordered->qualifyColumn($keyName) : $keyName;

        $base = $ordered instanceof EloquentBuilder ? $ordered->getQuery() : $ordered;

        $base->orders = [
            [
                'column' => $qualifiedColumn,
                'direction' => 'asc',
            ],
            ...$this->tieBreakers($base->orders ?? [], $column),
        ];

        return $ordered
            ->orderBy($qualifiedKey)
            ->lockForUpdate()
            ->pluck($qualifiedColumn, $qualifiedKey)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function tieBreakers(array $orders, string $column): array
    {
        return array_values(array_filter(
            $orders,
            fn (array $order): bool => ! is_string($order['column'] ?? null) || Str::afterLast($order['column'], '.') !== $column,
        ));
    }

    private function freshQuery(EloquentBuilder|BaseQueryBuilder $query): EloquentBuilder|BaseQueryBuilder
    {
        if ($query instanceof EloquentBuilder) {
            return $query->getModel()->newQueryWithoutScopes();
        }

        return $query->newQuery()->from($query->from);
    }
}
