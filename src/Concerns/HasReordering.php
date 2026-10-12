<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use LogicException;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

trait HasReordering
{
    protected ?string $reorderColumn = null;

    protected ?string $reorderUrl = null;

    public function reorderable(string $column = 'sort_order', ?string $url = null): self
    {
        $this->reorderColumn = $column;
        $this->reorderUrl = $url;

        return $this;
    }

    public function isReorderable(): bool
    {
        return ! is_null($this->reorderColumn);
    }

    public function getReorderColumn(): ?string
    {
        return $this->reorderColumn;
    }

    public function moveRow(int|string $id, int|string $toId): void
    {
        throw new LogicException('Rows can only be reordered on a table that is built from a query.');
    }

    protected function reorderKeyName(): string
    {
        return $this->rowKeyName();
    }

    /**
     * @return array{column: string, key: string, url: string|null}|null
     */
    protected function reorderPayload(): ?array
    {
        if (! $this->isReorderable()) {
            return null;
        }

        return [
            'column' => $this->reorderColumn,
            'key' => $this->reorderKeyName(),
            'url' => $this->reorderUrl ?? $this->signedReorderUrl(),
        ];
    }

    private function signedReorderUrl(): ?string
    {
        if (is_null($this->configurator) || ! app('router')->has('table.reorder')) {
            return null;
        }

        return SignedTableUrl::make('table.reorder', $this->configurator::class, $this->configurator->signedParameters());
    }
}
