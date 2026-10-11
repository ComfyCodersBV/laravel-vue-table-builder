<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use TranquilTools\TableBuilder\Http\Controllers\TableBulkActionController;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

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
        bool|string $requirePassword = false,
        public array $tableParameters = [],
    ) {
        if ($requirePassword === true) {
            $this->requirePassword = 'password';
        }

        if (is_string($requirePassword) && $requirePassword !== '') {
            $this->requirePassword = $requirePassword;
        }

        $this->url = $this->getUrl();
    }

    public function getSlug(): string
    {
        return Str::slug($this->label);
    }

    public function getUrl(): string
    {
        /** @var Route $route */
        $route = app('router')->getRoutes()->getByAction(TableBulkActionController::class);

        /** @var array */
        $currentQuery = app()->bound('request') ? collect(request()->query())
            ->except(['signature', 'expires', 'table', 'params', 'action', 'slug'])
            ->toArray() : [];

        return SignedTableUrl::make($route->getName(), $this->tableClass, $this->tableParameters, array_merge($currentQuery, [
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
