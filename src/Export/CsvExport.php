<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Export;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Str;
use NumberFormatter;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

final class CsvExport
{
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    private const FORMULA_PREFIXES = [
        '=',
        '+',
        '-',
        '@',
        "\t",
        "\r",
    ];

    private const DECIMAL_COMMA_LANGUAGES = [
        'af', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'es', 'et', 'eu', 'fi', 'fr', 'gl', 'hr', 'hu', 'id', 'is', 'it',
        'lt', 'lv', 'nb', 'nl', 'nn', 'no', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sr', 'sv', 'tr', 'uk', 'vi',
    ];

    public function __construct(
        public readonly string $delimiter = ',',
        public readonly string $decimalSeparator = '.',
    ) {}

    public static function make(?string $delimiter = null, ?string $decimalSeparator = null, ?string $locale = null): self
    {
        $decimalSeparator ??= self::configured('decimal_separator')
            ?? self::decimalSeparatorFor($locale ?? app()->getLocale());

        $delimiter ??= self::configured('delimiter')
            ?? ($decimalSeparator === ',' ? ';' : ',');

        return new self($delimiter, $decimalSeparator);
    }

    private static function configured(string $key): ?string
    {
        $value = config("vue-table-builder.export.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function decimalSeparatorFor(string $locale): string
    {
        if (class_exists(NumberFormatter::class)) {
            $symbol = (new NumberFormatter($locale, NumberFormatter::DECIMAL))->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

            if (in_array($symbol, [',', '.'], true)) {
                return $symbol;
            }
        }

        $language = strtolower(Str::before(str_replace('-', '_', $locale), '_'));

        return in_array($language, self::DECIMAL_COMMA_LANGUAGES, true) ? ',' : '.';
    }

    /**
     * @param  array<int, mixed>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, self::BYTE_ORDER_MARK);

            $this->write($handle, $headings);

            foreach ($rows as $row) {
                $this->write($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $cells
     */
    private function write($handle, array $cells): void
    {
        fputcsv($handle, array_map($this->cell(...), $cells), $this->delimiter, '"', '');
    }

    public function cell(mixed $value): string
    {
        return match (true) {
            is_null($value) => '',
            is_bool($value) => trans('vue-table-builder::table.export.'.($value ? 'yes' : 'no')),
            is_int($value) => (string) $value,
            is_float($value) => $this->decimal((string) $value),
            $value instanceof BackedEnum => $this->text((string) $value->value),
            $value instanceof UnitEnum => $this->text($value->name),
            $value instanceof DateTimeInterface => $this->date($value),
            is_array($value) => $this->text(implode(', ', array_map($this->cell(...), $value))),
            $value instanceof Stringable, is_string($value) => $this->text((string) $value),
            default => '',
        };
    }

    private function date(DateTimeInterface $date): string
    {
        return $date->format('H:i:s') === '00:00:00'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d H:i:s');
    }

    private function decimal(string $number): string
    {
        return str_replace('.', $this->decimalSeparator, $number);
    }

    private function text(string $text): string
    {
        if (preg_match('/^-?\d+\.\d+$/', $text) === 1) {
            return $this->decimal($text);
        }

        if (is_numeric($text) || ! Str::startsWith($text, self::FORMULA_PREFIXES)) {
            return $text;
        }

        return "'".$text;
    }
}
