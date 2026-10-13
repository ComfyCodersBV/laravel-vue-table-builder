<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonSerializable;
use TranquilTools\TableBuilder\Http\Controllers\TableBulkActionController;
use TranquilTools\TableBuilder\Routing\SignedTableUrl;

class BulkAction implements JsonSerializable
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
        public array $choices = [],
        public string $choicesLabel = '',
    ) {
        if ($requirePassword === true) {
            $this->requirePassword = 'password';
        }

        if (is_string($requirePassword) && $requirePassword !== '') {
            $this->requirePassword = $requirePassword;
        }

        $this->url = $this->getUrl();
    }

    public function hasChoices(): bool
    {
        return $this->choices !== [];
    }

    /**
     * @return list<string>
     */
    public function choiceValues(): array
    {
        return array_map(fn (string|int $value): string => (string) $value, array_keys($this->choices));
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
        $payload = [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->getUrl(),
            'confirm' => $this->confirm,
            'confirmText' => $this->confirmText,
            'confirmButton' => $this->confirmButton,
            'cancelButton' => $this->cancelButton,
            'requirePassword' => $this->requirePassword,
        ];

        return [
            ...$payload,
            ...$this->choicesPayload(),
        ];
    }

    public function jsonSerialize(): array
    {
        return [
            ...Arr::except(get_object_vars($this), [
                'beforeCallback',
                'eachCallback',
                'afterCallback',
                'choices',
                'choicesLabel',
            ]),
            ...$this->choicesPayload(),
        ];
    }

    /**
     * @return array{choices?: list<array{value: string, label: string}>, choicesLabel?: string}
     */
    private function choicesPayload(): array
    {
        if (! $this->hasChoices()) {
            return [];
        }

        return [
            'choices' => array_map(
                fn (string|int $value, string $label): array => [
                    'value' => (string) $value,
                    'label' => $label,
                ],
                array_keys($this->choices),
                $this->choices,
            ),
            'choicesLabel' => $this->choicesLabel,
        ];
    }
}
