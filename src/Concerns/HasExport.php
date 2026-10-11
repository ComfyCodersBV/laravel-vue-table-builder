<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TranquilTools\TableBuilder\Components\Column;
use TranquilTools\TableBuilder\Export\CsvExport;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

trait HasExport
{
    /**
     * @var array{label: string|null, filename: string|Closure|null, columns: array<int|string, string>|null, delimiter: string|null, decimalSeparator: string|null, url: string|null}|null
     */
    protected ?array $exportOptions = null;

    /**
     * @param  array<int|string, string>|null  $columns
     */
    public function exportable(
        ?string $label = null,
        string|Closure|null $filename = null,
        ?array $columns = null,
        ?string $delimiter = null,
        ?string $decimalSeparator = null,
        ?string $url = null,
    ): self {
        $this->exportOptions = [
            'label' => $label,
            'filename' => $filename,
            'columns' => $columns,
            'delimiter' => $delimiter,
            'decimalSeparator' => $decimalSeparator,
            'url' => $url,
        ];

        return $this;
    }

    public function isExportable(): bool
    {
        return ! is_null($this->exportOptions);
    }

    public function export(?Request $request = null): StreamedResponse
    {
        $options = $this->exportOptions ?? [];
        $columns = $this->exportColumns();

        return CsvExport::make($options['delimiter'] ?? null, $options['decimalSeparator'] ?? null)->download(
            $this->exportFilename($request ?? $this->request),
            array_map(fn (Column $column): string => $column->label, $columns),
            $this->exportRows($columns, $request),
        );
    }

    /**
     * @return iterable<int, mixed>
     */
    protected function exportItems(?Request $request): iterable
    {
        $this->loadResource();

        return $this->resource;
    }

    /**
     * @param  array<int, Column>  $columns
     * @return iterable<int, array<int, mixed>>
     */
    private function exportRows(array $columns, ?Request $request): iterable
    {
        foreach ($this->exportItems($request) as $item) {
            yield array_map(fn (Column $column): mixed => $column->exportValue($item), $columns);
        }
    }

    /**
     * @return array<int, Column>
     */
    public function exportColumns(): array
    {
        $selection = $this->exportOptions['columns'] ?? null;

        if (is_null($selection)) {
            return $this->columns
                ->filter(fn (Column $column): bool => $column->export)
                ->values()
                ->all();
        }

        $columns = [];

        foreach ($selection as $key => $label) {
            $columns[] = is_int($key)
                ? $this->exportColumn($label, null)
                : $this->exportColumn($key, $label);
        }

        return $columns;
    }

    private function exportColumn(string $key, ?string $label): Column
    {
        $column = $this->columns->first(fn (Column $column): bool => $column->key === $key);

        if (is_null($column)) {
            return new Column(
                key: $key,
                label: $label ?? Str::headline(str_replace('.', ' ', $key)),
                canBeHidden: false,
                hidden: false,
                sortable: false,
                sorted: false,
                highlight: false,
            );
        }

        if (is_null($label)) {
            return $column;
        }

        $renamed = $column->clone();
        $renamed->label = $label;

        return $renamed;
    }

    private function exportFilename(Request $request): string
    {
        $filename = $this->exportOptions['filename'] ?? null;

        if ($filename instanceof Closure) {
            $filename = call_user_func($filename, $request);
        }

        if (! is_string($filename) || $filename === '') {
            $filename = Str::slug($this->name === static::DEFAULT_NAME ? 'export' : $this->name).'-'.now()->format('Y-m-d');
        }

        return Str::finish($filename, '.csv');
    }

    /**
     * @return array{label: string, url: string|null}|null
     */
    protected function exportPayload(): ?array
    {
        if (! $this->isExportable()) {
            return null;
        }

        return [
            'label' => $this->exportOptions['label'] ?? trans('vue-table-builder::table.export.button'),
            'url' => $this->exportOptions['url'] ?? $this->signedExportUrl(),
        ];
    }

    private function signedExportUrl(): ?string
    {
        if (is_null($this->configurator) || ! app('router')->has('table.export')) {
            return null;
        }

        return SignedTableUrl::make('table.export', $this->configurator::class, $this->configurator->signedParameters());
    }
}
