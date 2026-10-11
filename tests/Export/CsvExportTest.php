<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use TranquilTools\TableBuilder\Export\CsvExport;

enum ExportStatus: string
{
    case Open = 'open';
}

it('neutralises cells that a spreadsheet would run as a formula', function (string $value, string $expected) {
    expect((new CsvExport)->cell($value))->toBe($expected);
})->with([
    'equals' => ['=HYPERLINK("http://evil")', "'=HYPERLINK(\"http://evil\")"],
    'plus' => ['+cmd|calc', "'+cmd|calc"],
    'minus' => ['-2+3', "'-2+3"],
    'at' => ['@SUM(A1)', "'@SUM(A1)"],
    'tab' => ["\tlead", "'\tlead"],
    'carriage return' => ["\rlead", "'\rlead"],
    'negative number' => ['-12', '-12'],
    'phone number' => ['+31612345678', '+31612345678'],
    'plain text' => ['Chair', 'Chair'],
]);

it('writes numbers with the decimal separator', function () {
    $csv = new CsvExport(';', ',');

    expect($csv->cell(12.5))->toBe('12,5')
        ->and($csv->cell('1250.75'))->toBe('1250,75')
        ->and($csv->cell('-3.5'))->toBe('-3,5')
        ->and($csv->cell(1250))->toBe('1250');
});

it('writes booleans, dates, enums and empty values readably', function () {
    $csv = new CsvExport;

    expect($csv->cell(true))->toBe('Yes')
        ->and($csv->cell(false))->toBe('No')
        ->and($csv->cell(null))->toBe('')
        ->and($csv->cell(CarbonImmutable::parse('2026-10-11 00:00:00')))->toBe('2026-10-11')
        ->and($csv->cell(CarbonImmutable::parse('2026-10-11 14:30:00')))->toBe('2026-10-11 14:30:00')
        ->and($csv->cell(ExportStatus::Open))->toBe('open')
        ->and($csv->cell(['a', 'b']))->toBe('a, b');
});

it('derives the separators from the locale', function () {
    expect(CsvExport::make(locale: 'nl'))
        ->delimiter->toBe(';')
        ->decimalSeparator->toBe(',')
        ->and(CsvExport::make(locale: 'en'))
        ->delimiter->toBe(',')
        ->decimalSeparator->toBe('.')
        ->and(CsvExport::decimalSeparatorFor('de_DE'))->toBe(',');
});

it('lets the config and the table override the separators', function () {
    config()->set('vue-table-builder.export.delimiter', "\t");

    expect(CsvExport::make(locale: 'nl'))
        ->delimiter->toBe("\t")
        ->decimalSeparator->toBe(',')
        ->and(CsvExport::make(delimiter: '|', decimalSeparator: '.', locale: 'nl'))
        ->delimiter->toBe('|')
        ->decimalSeparator->toBe('.');
});
