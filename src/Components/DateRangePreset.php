<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DateRangePreset
{
    public const BUILT_IN = [
        'today',
        'upcoming',
        'past',
        'this_week',
        'last_week',
        'next_week',
        'this_month',
        'last_month',
        'next_month',
        'this_year',
    ];

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        private readonly ?Closure $range = null,
    ) {
        if ($key === '' || str_contains($key, '..')) {
            throw new InvalidArgumentException("Date range preset key [{$key}] must be non-empty and cannot contain '..'.");
        }

        if (is_null($range) && ! in_array($key, self::BUILT_IN, true)) {
            throw new InvalidArgumentException("Date range preset [{$key}] is not built in and needs a range callback.");
        }
    }

    public static function make(string $key, ?string $label = null, ?Closure $range = null): self
    {
        return new self($key, $label ?? self::defaultLabel($key), $range);
    }

    private static function defaultLabel(string $key): string
    {
        if (! in_array($key, self::BUILT_IN, true)) {
            return Str::headline($key);
        }

        return trans("vue-table-builder::table.date_presets.{$key}");
    }

    /**
     * @return array{from: CarbonImmutable|null, until: CarbonImmutable|null}
     */
    public function range(CarbonImmutable $now): array
    {
        [$from, $until] = is_null($this->range)
            ? $this->builtInRange($now)
            : call_user_func($this->range, $now);

        return [
            'from' => self::immutable($from),
            'until' => self::immutable($until),
        ];
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function builtInRange(CarbonImmutable $now): array
    {
        $today = $now->startOfDay();
        $week = $now->startOfWeek();
        $month = $now->startOfMonth();

        return match ($this->key) {
            'today' => [$today, $today->addDay()],
            'upcoming' => [$now, null],
            'past' => [null, $now],
            'this_week' => [$week, $week->addWeek()],
            'last_week' => [$week->subWeek(), $week],
            'next_week' => [$week->addWeek(), $week->addWeeks(2)],
            'this_month' => [$month, $month->addMonthNoOverflow()],
            'last_month' => [$month->subMonthNoOverflow(), $month],
            'next_month' => [$month->addMonthNoOverflow(), $month->addMonthsNoOverflow(2)],
            'this_year' => [$now->startOfYear(), $now->startOfYear()->addYear()],
            default => throw new InvalidArgumentException("Date range preset [{$this->key}] is not built in."),
        };
    }

    private static function immutable(mixed $value): ?CarbonImmutable
    {
        if (is_null($value)) {
            return null;
        }

        if (! $value instanceof CarbonInterface) {
            throw new InvalidArgumentException('A date range preset must return Carbon instances or null.');
        }

        return $value->toImmutable();
    }

    /**
     * @return array{key: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
        ];
    }

    /**
     * @param  array<int|string, string|Closure|DateRangePreset>  $presets
     * @return array<string, DateRangePreset>
     */
    public static function normalize(array $presets): array
    {
        $normalized = [];

        foreach ($presets as $key => $preset) {
            $preset = self::fromDefinition($key, $preset);

            $normalized[$preset->key] = $preset;
        }

        return $normalized;
    }

    private static function fromDefinition(int|string $key, string|Closure|DateRangePreset $preset): self
    {
        if ($preset instanceof self) {
            return $preset;
        }

        if (is_int($key) && is_string($preset)) {
            return self::make($preset);
        }

        if ($preset instanceof Closure) {
            return self::make((string) $key, range: $preset);
        }

        return self::make((string) $key, $preset);
    }
}
