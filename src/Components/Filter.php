<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Carbon\CarbonImmutable;
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
        public ?string $endColumn = null,
        public ?string $timezone = null,
        public array $presets = [],
    ) {}

    public function clone(): static
    {
        return clone $this;
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

    public function preset(): ?DateRangePreset
    {
        return $this->presets[(string) $this->value] ?? null;
    }

    public function usesDateTimeBounds(): bool
    {
        return ! is_null($this->timezone) || ! is_null($this->endColumn) || ! is_null($this->preset());
    }

    public function displayTimezone(): string
    {
        return $this->timezone ?? (string) config('app.timezone', 'UTC');
    }

    /**
     * @return array{from: CarbonImmutable|null, until: CarbonImmutable|null}
     */
    public function dateTimeBounds(): array
    {
        $preset = $this->preset();

        if (! is_null($preset)) {
            return $preset->range(CarbonImmutable::now($this->displayTimezone()));
        }

        ['from' => $from, 'to' => $to] = $this->dateRange();

        return [
            'from' => is_null($from) ? null : $this->startOfDay($from),
            'until' => is_null($to) ? null : $this->startOfDay($to)->addDay(),
        ];
    }

    private function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->displayTimezone());
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
        $array = [
            'key' => $this->key,
            'label' => $this->label,
            'options' => $this->options(),
            'value' => $this->value,
            'type' => $this->type,
        ];

        if ($this->presets === []) {
            return $array;
        }

        return [
            ...$array,
            'presets' => array_values(array_map(fn (DateRangePreset $preset): array => $preset->toArray(), $this->presets)),
        ];
    }
}
