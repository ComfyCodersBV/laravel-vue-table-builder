<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;

trait HasExports
{
    protected array $exports = [];

    public function getExports(): array
    {
        return $this->exports;
    }

    public function hasExports(): bool
    {
        return ! empty($this->exports);
    }

    public function export(
        ?string $label = null,
        ?string $filename = null,
        ?string $type = null,
        array $events = [],
    ): self {
        if (! class_exists(Excel::class)) {
            throw new LaravelExcelException(
                "To generate exports from a Splade Table, please install the 'maatwebsite/excel' package."
            );
        }

        if ($filename === null) {
            $filename = $this->configurator
                ? Str::slug(class_basename($this->configurator)) . '.xlsx'
                : 'export.xlsx';
        }

        $key = count($this->exports);

        $this->exports[$key] = new Export(
            key: $key,
            label: $label ?: __('Excel Export'),
            filename: $filename,
            type: $type ?: Excel::XLSX,
            tableClass: get_class($this->configurator),
            events: $events,
        );

        return $this;
    }
}
