<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Components\RowAction;

trait HasRowActions
{
    /**
     * @var array<string, RowAction>
     */
    protected array $rowActions = [];

    public function rowAction(
        string $label,
        string|Closure|null $url = null,
        ?string $route = null,
        string $method = 'get',
        ?string $confirm = null,
        string $variant = 'outline',
        bool|Closure $visible = true,
        bool|string|Closure $disabled = false,
        ?string $key = null,
    ): self {
        $key = $key ?: Str::slug($label);

        $this->rowActions[$key] = new RowAction(
            key: $key,
            label: $label,
            url: $url,
            route: $route,
            method: $method,
            confirm: $confirm,
            variant: $variant,
            visible: $visible,
            disabled: $disabled,
        );

        return $this;
    }

    /**
     * @return array<string, RowAction>
     */
    public function getRowActions(): array
    {
        return $this->rowActions;
    }

    public function hasRowActions(): bool
    {
        return $this->rowActions !== [];
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function resolveRowActions(Collection $items): array
    {
        if (! $this->hasRowActions()) {
            return [];
        }

        return $items
            ->map(fn (mixed $item): array => Collection::make($this->rowActions)
                ->map(fn (RowAction $action): ?array => $action->resolve($item))
                ->filter()
                ->values()
                ->all())
            ->values()
            ->all();
    }
}
