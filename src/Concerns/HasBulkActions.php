<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use TranquilTools\TableBuilder\Components\BulkAction;

trait HasBulkActions
{
    protected array $bulkActions = [];

    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    public function hasBulkActions(): bool
    {
        return !empty($this->bulkActions);
    }

    public function bulkAction(
        string $label,
        ?callable $each = null,
        ?callable $before = null,
        ?callable $after = null,
        bool|string $confirm = '',
        string $confirmText = '',
        string $confirmButton = '',
        string $cancelButton = '',
        bool|string $requirePassword = false,
    ): self {
        $key = count($this->bulkActions);

        $this->bulkActions[$key] = new BulkAction(
            key: (string) $key,
            label: $label,
            tableClass: get_class($this->configurator),
            eachCallback: $each,
            beforeCallback: $before,
            afterCallback: $after,
            confirm: $confirm,
            confirmText: $confirmText,
            confirmButton: $confirmButton,
            cancelButton: $cancelButton,
            requirePassword: $requirePassword,
        );

        return $this;
    }
}