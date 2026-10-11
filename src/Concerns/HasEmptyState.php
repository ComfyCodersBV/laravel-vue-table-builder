<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

trait HasEmptyState
{
    /**
     * @var array{title: string|null, description: string|null, filteredTitle: string|null, filteredDescription: string|null}
     */
    protected array $emptyState = [
        'title' => null,
        'description' => null,
        'filteredTitle' => null,
        'filteredDescription' => null,
    ];

    public function emptyState(
        ?string $title = null,
        ?string $description = null,
        ?string $filteredTitle = null,
        ?string $filteredDescription = null,
    ): self {
        $this->emptyState = [
            'title' => $title,
            'description' => $description,
            'filteredTitle' => $filteredTitle,
            'filteredDescription' => $filteredDescription,
        ];

        return $this;
    }

    /**
     * @return array{title: string|null, description: string|null, filteredTitle: string|null, filteredDescription: string|null}
     */
    public function getEmptyState(): array
    {
        return $this->emptyState;
    }
}
