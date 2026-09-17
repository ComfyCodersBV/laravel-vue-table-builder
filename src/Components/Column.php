<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Column implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $canBeHidden,
        public bool $hidden,
        public bool|Closure $sortable,
        public bool|string $sorted,
        public bool $highlight,
        public array|string|null $classes = null,
        public ?Closure $as = null,
        public string $alignment = 'left',
        public bool $clickable = true,
        public bool $boolean = false,
    )
    {
        if (is_array($classes)) {
            $classes = self::flattenClasses($classes);
        }

        $this->classes = Arr::toCssClasses($classes);
    }

    private static function flattenClasses(array $classes): array
    {
        $flattened = [];

        foreach ($classes as $key => $value) {
            if (is_array($value)) {
                $flattened = array_merge($flattened, self::flattenClasses($value));

                continue;
            }

            if (is_string($key)) {
                $flattened[$key] = $value;

                continue;
            }

            $flattened[] = $value;
        }

        return $flattened;
    }

    public function clone(): static
    {
        return new static(
            $this->key,
            $this->label,
            $this->canBeHidden,
            $this->hidden,
            $this->sortable,
            $this->sorted,
            $this->highlight,
            $this->classes,
            $this->as,
            $this->alignment,
            $this->clickable,
            $this->boolean,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'can_be_hidden' => $this->canBeHidden,
            'hidden' => $this->hidden,
            'sortable' => $this->sortable !== false,
            'sorted' => $this->sorted,
            'highlight' => $this->highlight,
            'class' => $this->classes,
            'alignment' => $this->alignment,
            'clickable' => $this->clickable,
            'boolean' => $this->boolean,
        ];
    }

    public function getDataFromItem($item)
    {
        if ($this->isNested()) {
            $results = data_get($item, $this->relationshipName());

            if ($results instanceof Collection) {
                $key = $this->relationshipColumn();

                return $results->map->{$key}->implode(PHP_EOL);
            }
        }

        return data_get($item, $this->key, function () use ($item) {
            if (! is_object($item)) {
                return null;
            }

            return rescue(fn() => $item->{$this->key}, report: false);
        });
    }

    public function isNested(): bool
    {
        return Str::contains($this->key, '.');
    }

    public function relationshipName(): string
    {
        return Str::beforeLast($this->key, '.');
    }

    public function relationshipColumn(): string
    {
        return Str::afterLast($this->key, '.');
    }

}
