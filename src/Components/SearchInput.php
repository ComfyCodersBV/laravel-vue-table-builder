<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

class SearchInput
{
    const EXACT = 'exact';

    const WILDCARD = 'wildcard';

    const WILDCARD_LEFT = 'wildcard_left';

    const WILDCARD_RIGHT = 'wildcard_right';

    public function __construct(
        public string $key,
        public array $columns,
        public string $label,
        public ?string $value = null,
    ) {}

    public function clone(): static
    {
        return new static(
            $this->key,
            $this->columns,
            $this->label,
            $this->value,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'columns' => $this->columns,
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
