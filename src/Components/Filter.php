<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class Filter implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
        public ?string $value,
        public bool $noFilterOption,
        public string $noFilterOptionLabel,
        public string $type,
        public mixed $callback = null,
        public ?string $column = null,
    ) {}

    public function clone(): static
    {
        return new static(
            $this->key,
            $this->label,
            $this->options,
            $this->value,
            $this->noFilterOption,
            $this->noFilterOptionLabel,
            $this->type,
            $this->callback,
            $this->column,
        );
    }

    public function hasValue(): bool
    {
        return ! is_null($this->value);
    }

    public function isDateFilter(): bool
    {
        return in_array($this->type, ['date', 'date_range'], true);
    }

    /**
     * @return array{from: string|null, to: string|null}
     */
    public function dateRange(): array
    {
        if ($this->type === 'date') {
            $date = self::validDate($this->value);

            return [
                'from' => $date,
                'to' => $date,
            ];
        }

        $parts = explode('..', (string) $this->value, 2);

        return [
            'from' => self::validDate($parts[0]),
            'to' => self::validDate($parts[1] ?? null),
        ];
    }

    private static function validDate(?string $value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    public function options(): array
    {
        $options = $this->options;

        if ($this->noFilterOption) {
            $options = Arr::prepend($options, $this->noFilterOptionLabel, '');
        }

        return $options;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'options' => $this->options(),
            'value' => $this->value,
            'type' => $this->type,
        ];
    }
}
