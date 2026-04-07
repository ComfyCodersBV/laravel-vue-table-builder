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
        public bool|Closure $exportAs,
        public Closure|string|null $exportFormat = null,
        public Closure|array|null $exportStyling = null,
        public array|string|null $classes = null,
        public ?Closure $as = null,
        public string $alignment = 'left',
        public bool $clickable = true,
    )
    {
        if (is_array($classes)) {
            $classes = Arr::flatten($classes);
        }

        $this->classes = Arr::toCssClasses($classes);
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
            $this->exportAs,
            $this->exportFormat,
            $this->exportStyling,
            $this->classes,
            $this->as,
            $this->alignment,
            $this->clickable,
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
            'alignment' => $this->alignment,
            'clickable' => $this->clickable,
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

    public static function hashKey(string $name): string
    {
        return md5($name);
    }

    public function keyHash(): string
    {
        return static::hashKey($this->key);
    }
}
