<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Http\Controllers\TableBulkActionController;

class BulkAction
{
    public bool|string $requirePassword = false;

    public string $url = '';

    public function __construct(
        public string $key,
        public string $label,
        public string $tableClass,
        public ?Closure $beforeCallback = null,
        public ?Closure $eachCallback = null,
        public ?Closure $afterCallback = null,
        public bool|string $confirm = '',
        public string $confirmText = '',
        public string $confirmButton = '',
        public string $cancelButton = '',
        bool $requirePassword = false,
    )
    {
        if ($requirePassword === true) {
            $this->requirePassword = 'password';
        }

        $this->url = $this->getUrl();
    }

    public function getSlug(): string
    {
        return Str::slug($this->label);
    }

    public function getUrl(): string
    {
        /** @var Route */
        $route = app('router')->getRoutes()->getByAction(TableBulkActionController::class);

        /** @var array */
        $currentQuery = app()->bound('request') ? collect(request()->query())
            ->except(['signature', 'expires'])
            ->toArray() : [];

        return URL::signedRoute($route->getName(), array_merge($currentQuery, [
            'table' => base64_encode($this->tableClass),
            'action' => base64_encode($this->key),
            'slug' => $this->getSlug(),
        ]));
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->getUrl(),
            'confirm' => $this->confirm,
            'confirmText' => $this->confirmText,
            'confirmButton' => $this->confirmButton,
            'cancelButton' => $this->cancelButton,
            'requirePassword' => $this->requirePassword,
        ];
    }
}
