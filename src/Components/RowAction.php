<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Components;

use Closure;
use InvalidArgumentException;

class RowAction
{
    public const METHODS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
    ];

    public string $method;

    public function __construct(
        public string $key,
        public string $label,
        public string|Closure|null $url = null,
        public ?string $route = null,
        string $method = 'get',
        public ?string $confirm = null,
        public string $variant = 'outline',
        public bool|Closure $visible = true,
        public bool|string|Closure $disabled = false,
        public bool|string $download = false,
    ) {
        $this->method = strtolower($method);

        if (! in_array($this->method, self::METHODS, true)) {
            throw new InvalidArgumentException('Row action method must be one of: '.implode(', ', self::METHODS).'.');
        }

        if ($download !== false && $this->method !== 'get') {
            throw new InvalidArgumentException("Row action [{$label}] can only download with the get method.");
        }

        if (is_null($url) && is_null($route)) {
            throw new InvalidArgumentException("Row action [{$label}] needs a url or a route.");
        }
    }

    /**
     * @return array{key: string, label: string, url: string, method: string, confirm: string|null, variant: string, disabled: bool, disabledReason: string|null, download?: bool|string}|null
     */
    public function resolve(mixed $item): ?array
    {
        if (! $this->isVisibleFor($item)) {
            return null;
        }

        $url = $this->urlFor($item);

        if (is_null($url) || $url === '') {
            return null;
        }

        $disabled = $this->disabled instanceof Closure
            ? call_user_func($this->disabled, $item)
            : $this->disabled;

        $resolved = [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $url,
            'method' => $this->method,
            'confirm' => $this->confirm,
            'variant' => $this->variant,
            'disabled' => is_string($disabled) ? $disabled !== '' : (bool) $disabled,
            'disabledReason' => is_string($disabled) && $disabled !== '' ? $disabled : null,
        ];

        if ($this->download === false || $this->download === '') {
            return $resolved;
        }

        return [
            ...$resolved,
            'download' => $this->download,
        ];
    }

    private function isVisibleFor(mixed $item): bool
    {
        if ($this->visible instanceof Closure) {
            return (bool) call_user_func($this->visible, $item);
        }

        return $this->visible;
    }

    private function urlFor(mixed $item): ?string
    {
        if ($this->url instanceof Closure) {
            return call_user_func($this->url, $item);
        }

        if (is_string($this->url)) {
            return $this->url;
        }

        return route($this->route, $item);
    }
}
