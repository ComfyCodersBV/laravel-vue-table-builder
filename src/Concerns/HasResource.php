<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Concerns;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasResource
{
    public $rowLinkCallable;

    public string $rowLinkType = '';

    public string $rowLinkTarget = '_self';

    protected string $primaryKey = '';

    public function rowLink(callable $callback, bool $modal = false, bool $href = false, bool $newTab = false): self
    {
        $this->rowLinkCallable = $callback;

        $this->rowLinkType = $modal ? 'modal' : ($href ? 'href' : 'link');
        $this->rowLinkTarget = $newTab ? '_blank' : '_self';

        return $this;
    }

    protected function resolveRowLinks(): self
    {
        if (! $this->rowLinkCallable) {
            return $this;
        }

        $this->loadResource();

        $collection = $this->resource instanceof LengthAwarePaginator
            ? $this->resource->items()
            : $this->resource;

        $this->rowLinks = Collection::make($collection)->map($this->rowLinkCallable);

        return $this;
    }

    public function rowModal(callable $callback): self
    {
        return $this->rowLink($callback, modal: true);
    }

    public function perPage(): int
    {
        $this->loadResource();

        if ($this->resource instanceof LengthAwarePaginator) {
            return $this->resource->perPage();
        }

        return count($this->resource);
    }

    public function totalOnThisPage()
    {
        $this->loadResource();

        if ($this->resource instanceof LengthAwarePaginator) {
            return count($this->resource->items());
        }

        return count($this->resource);
    }

    public function totalOnAllPages()
    {
        $this->loadResource();

        if ($this->resource instanceof LengthAwarePaginator) {
            return $this->resource->total();
        }

        return count($this->resource);
    }

    public function isEmpty(): bool
    {
        $this->loadResource();

        return count($this->resource) === 0;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function primaryKey(string $key): self
    {
        $this->primaryKey = $key;

        return $this;
    }

    public function findPrimaryKey($item)
    {
        if ($this->primaryKey) {
            return data_get($item, $this->primaryKey);
        }

        if ($item instanceof Model) {
            return $item->getKey();
        }

        throw new Exception('No primary key configured');
    }

    public function getPrimaryKeys(): array
    {
        $this->loadResource();

        $ids = [];

        foreach ($this->resource as $item) {
            $ids[] = $this->findPrimaryKey($item);
        }

        return $ids;
    }
}
