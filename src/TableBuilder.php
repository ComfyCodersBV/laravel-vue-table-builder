<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use JsonSerializable;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use TranquilTools\TableBuilder\Components\Column;
use TranquilTools\TableBuilder\Concerns\HasBulkActions;
use TranquilTools\TableBuilder\Concerns\HasColumns;
use TranquilTools\TableBuilder\Concerns\HasFilters;
use TranquilTools\TableBuilder\Concerns\HasResource;
use TranquilTools\TableBuilder\Concerns\HasSearchInputs;
use TranquilTools\TableBuilder\Exceptions\PaginationException;

class TableBuilder implements Arrayable, JsonSerializable
{
    use Conditionable;
    use HasBulkActions;
    use HasColumns;
    use HasFilters;
    use HasResource;
    use HasSearchInputs;

    const DEFAULT_NAME = 'default';

    const GLOBAL_SEARCH_KEY = 'global';

    protected string $name;

    protected array $perPageOptions = [];

    protected string $defaultSort = '';

    protected string $cellClass = '';

    protected string $headClass = '';

    protected Request $request;

    protected $resource;

    protected Collection $columns;

    protected Collection $filters;

    protected Collection $searchInputs;

    protected Collection $rowLinks;

    protected static array $defaultPerPageOptions = [10, 25, 50, 100];

    protected static int $defaultPerPage = 25;

    protected static int $defaultSearchDebounce = 350;

    protected static bool $hidePaginationWhenResourceContainsOnePage = false;

    protected ?AbstractTable $configurator = null;

    protected bool $resourceLoaded = false;

    public static string $defaultPaginationScroll = 'top';

    protected static bool $defaultResetButton = true;

    public function __construct($resource, ?Request $request = null)
    {
        $this->request = $request ?: request();

        $this->resource = $resource;

        $this->columns = new Collection;
        $this->filters = new Collection;
        $this->searchInputs = new Collection;
        $this->rowLinks = new Collection;

        $this->name(static::DEFAULT_NAME);

        if (static::$defaultGlobalSearch !== false) {
            $this->withGlobalSearch(static::$defaultGlobalSearch);
        }

        $this->perPageOptions(static::$defaultPerPageOptions);
    }

    public static function for($resource): QueryBuilder|static
    {
        if (is_string($resource)) {
            $resource = app($resource);
        }

        if ($resource instanceof Model) {
            $resource = $resource->newQuery();
        }

        if ($resource instanceof Relation) {
            $resource = $resource->getQuery();
        }

        if ($resource instanceof Builder || $resource instanceof SpatieQueryBuilder) {
            return new QueryBuilder($resource);
        }

        // If it's an Eloquent Builder, wrap it with QueryBuilder
        if ($resource instanceof \Illuminate\Database\Eloquent\Builder) {
            return new QueryBuilder($resource);
        }

        return new static($resource);
    }

    public function setConfigurator(AbstractTable $configurator): self
    {
        $this->configurator = $configurator;

        return $this;
    }

    protected function query(string $key, $default = null)
    {
        return $this->request->query(
            $this->name === static::DEFAULT_NAME ? $key : "{$this->name}_{$key}",
            $default
        );
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function perPageOptions(array $perPageOptions): self
    {
        $this->perPageOptions = $perPageOptions;

        return $this;
    }

    public static function defaultPerPageOptions(array $perPageOptions): void
    {
        static::$defaultPerPageOptions = $perPageOptions;
    }

    public static function defaultPerPage(int $perPage): void
    {
        static::$defaultPerPage = $perPage;
    }

    public static function getDefaultPerPage(): int
    {
        return static::$defaultPerPage;
    }

    public static function getDefaultSearchDebounce(): int
    {
        return static::$defaultSearchDebounce;
    }

    public static function defaultSearchDebounce(int $milliseconds)
    {
        static::$defaultSearchDebounce = max(0, $milliseconds);
    }

    public static function getDefaultResetButton(): bool
    {
        return static::$defaultResetButton;
    }

    public static function defaultResetButton(bool $value = true)
    {
        static::$defaultResetButton = $value;
    }

    public static function getDefaultPaginationScroll(): string
    {
        return static::$defaultPaginationScroll;
    }

    public static function defaultPaginationScroll(string $value)
    {
        static::$defaultPaginationScroll = $value;
    }

    public static function hidesPaginationWhenResourceContainsOnePage(): bool
    {
        return static::$hidePaginationWhenResourceContainsOnePage;
    }

    public static function hidePaginationWhenResourceContainsOnePage(bool $value = true)
    {
        static::$hidePaginationWhenResourceContainsOnePage = $value;
    }

    public function isSorted(): bool
    {
        return (bool) $this->query('sort');
    }

    public function page(): int
    {
        return Paginator::resolveCurrentPage();
    }

    public function allPerPageOptions(): array
    {
        return collect($this->perPageOptions)
            ->push($this->perPage())
            ->unique()
            ->sort()
            ->all();
    }

    public function perPage(): int
    {
        return $this->perPageOptions[0] ?? 15;
    }

    public function column(
        string $key,
        string $label = '',
        bool $canBeHidden = true,
        bool $hidden = false,
        bool $sortable = false,
        bool $searchable = false,
        string $alignment = 'left',
        ?callable $as = null,
        bool $clickable = true,
    ): self
    {
        $sorted = false;

        // Check if this column is currently being sorted
        $sortQuery = $this->query('sort');
        if ($sortQuery) {
            $sortKey = ltrim($sortQuery, '-');
            if ($sortKey === $key) {
                $sorted = Str::startsWith($sortQuery, '-') ? 'desc' : 'asc';
            }
        }

        $this->columns->push(new Column(
            key: $key,
            label: $label ?: Str::headline($key),
            canBeHidden: $canBeHidden,
            hidden: $hidden,
            sortable: $sortable,
            sorted: $sorted,
            highlight: false,
            as: $as,
            alignment: $alignment,
            clickable: $clickable,
        ));

        return $this;
    }

    public function columns(): Collection
    {
        return $this->columns;
    }

    private function toItemArray($item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return (array) $item;
    }

    protected function transformItem($item): array
    {
        $itemArray = $this->toItemArray($item);

        $this->columns->each(function (Column $column) use (&$itemArray, $item) {
            if (! is_callable($column->as)) {
                return;
            }

            $value = $column->getDataFromItem($itemArray);
            $transformed = call_user_func($column->as, $value, $item);

            if ($transformed instanceof HtmlString) {
                data_set($itemArray, $column->key, $transformed->toHtml());

                return;
            }

            if (is_string($transformed)) {
                $transformed = e($transformed);
            }

            data_set($itemArray, $column->key, $transformed);
        });

        return $itemArray;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        // Ensure the resource is loaded before converting to array
        $this->beforeRender();

        $data = $this->resource;
        $pagination = null;

        // Extract pagination data if the resource is paginated
        if (is_object($data) && method_exists($data, 'toArray')) {
            $paginationData = $data->toArray();

            if (isset($paginationData['data'])) {
                $pagination = [
                    'current_page' => $paginationData['current_page'] ?? 1,
                    'from' => $paginationData['from'] ?? null,
                    'to' => $paginationData['to'] ?? null,
                    'total' => $paginationData['total'] ?? 0,
                    'per_page' => $paginationData['per_page'] ?? 15,
                    'last_page' => $paginationData['last_page'] ?? 1,
                    'links' => $paginationData['links'] ?? [],
                    'first_page_url' => $paginationData['first_page_url'] ?? null,
                    'last_page_url' => $paginationData['last_page_url'] ?? null,
                    'next_page_url' => $paginationData['next_page_url'] ?? null,
                    'prev_page_url' => $paginationData['prev_page_url'] ?? null,
                ];

                $data = method_exists($data, 'items') ? collect($data->items()) : collect($paginationData['data']);
            }
        }

        $items = $data instanceof Collection ? $data : (is_array($data) ? collect($data) : null);

        if ($items !== null) {
            $data = $items->map(fn($item) => $this->transformItem($item))->all();
        }

        return [
            'data' => $data ?? [],
            'columns' => $this->columns->map->toArray()->toArray(),
            'pagination' => $pagination,
            'filters' => $this->filters()->values(),
            'searchInputs' => $this->searchInputs()->map->toArray()->toArray(),
            'perPageOptions' => $this->perPageOptions,
            'defaultSort' => $this->defaultSort,
            'bulkActions' => $this->bulkActions,
            'rowLinks' => $this->rowLinks->toArray(),
            'rowLinkType' => $this->rowLinkType,
            'cellClass' => $this->cellClass,
            'headClass' => $this->headClass,
        ];
    }

    public function class(string $cell = '', string $head = ''): self
    {
        $this->cellClass = $cell;
        $this->headClass = $head;

        return $this;
    }

    public function defaultSort(string $sort, string $direction = ''): self
    {
        if ($direction && ! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Direction must be "asc" or "desc".');
        }

        if (Str::startsWith($sort, '-')) {
            $sort = Str::after($sort, '-');
            $direction = $direction ?: 'desc';
        }

        $this->defaultSort = $direction === 'desc' ? "-{$sort}" : $sort;

        return $this;
    }

    public function defaultSortDesc(string $sort): self
    {
        return $this->defaultSort($sort, 'desc');
    }

    public function getDefaultSort(): string
    {
        return $this->defaultSort;
    }

    public function hasPerPageQuery(): bool
    {
        return $this->query('perPage') !== null;
    }

    public function beforeRender(): self
    {
        return $this->loadResource()->resolveRowLinks();
    }

    public function loadResource(): self
    {
        if (! $this->resourceLoaded) {
            $this->resourceLoaded = true;
            $this->filterCollectionResource();
        }

        return $this;
    }

    private function filterCollectionResource(): void
    {
        if (! $this->resource instanceof Collection) {
            return;
        }

        $this->searchInputs()->filter->value->each(function ($searchInput) {
            $term = strtolower($searchInput->value);

            $this->resource = $this->resource->filter(function ($item) use ($searchInput, $term) {
                foreach (array_keys($searchInput->columns) as $column) {
                    $value = strtolower((string) data_get($item, $column, ''));

                    if (str_contains($value, $term)) {
                        return true;
                    }
                }

                return false;
            })->values();
        });
    }

    public function performBulkAction(callable $action, array $ids) {}

    private function preventPaginationCall()
    {
        throw new PaginationException(
            'You should call the paginate-method on the resource, or pass a query builder as a resource so you can work with the Query Builder.'
        );
    }

    public function paginate($perPage = null)
    {
        $this->preventPaginationCall();
    }

    public function simplePaginate($perPage = null)
    {
        $this->preventPaginationCall();
    }

    public function cursorPaginate($perPage = null)
    {
        $this->preventPaginationCall();
    }
}
