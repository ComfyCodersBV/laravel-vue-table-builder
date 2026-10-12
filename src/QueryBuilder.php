<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Kirschbaum\PowerJoins\EloquentJoins;
use LogicException;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use TranquilTools\TableBuilder\Actions\ReorderRows;
use TranquilTools\TableBuilder\Components\Column;
use TranquilTools\TableBuilder\Components\Filter;
use TranquilTools\TableBuilder\Components\SearchInput;
use TranquilTools\TableBuilder\Exceptions\PowerJoinsException;

class QueryBuilder extends TableBuilder
{
    private $paginateMethod;

    private $perPage;

    private bool $ignoreCase = true;

    protected bool $parseTerms = true;

    public function __construct(private BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder $builder, ?Request $request = null)
    {
        parent::__construct([], $request);
    }

    protected function shouldDeriveName(): bool
    {
        return ! $this->builder instanceof SpatieQueryBuilder;
    }

    protected function shouldFilterResourceInMemory(): bool
    {
        return false;
    }

    public function parseTerms(bool $state = true): self
    {
        $this->parseTerms = $state;

        return $this;
    }

    public function ignoreCase(bool $state = true): self
    {
        $this->ignoreCase = $state;

        return $this;
    }

    public function noPagination(): self
    {
        return $this->setPagination('', null);
    }

    private function setPagination(string $method, ?int $perPage = null): self
    {
        $this->paginateMethod = $method;

        $this->perPage = $perPage;

        return $this;
    }

    public function paginate($perPage = null): self
    {
        return $this->setPagination('paginate', $perPage);
    }

    public function simplePaginate($perPage = null): self
    {
        return $this->setPagination('simplePaginate', $perPage);
    }

    public function cursorPaginate($perPage = null): self
    {
        return $this->setPagination('cursorPaginate', $perPage);
    }

    public function parseTermsIntoCollection(string $terms): Collection
    {
        return Collection::make(str_getcsv($terms, ' ', '"', '\\'))
            ->reject(function ($term = null) {
                return is_null($term) || trim($term) === '';
            })
            ->values();
    }

    private function getTermAndWhereOperator(EloquentBuilder $builder, string $term, ?string $searchMethod = null): array
    {
        $like = 'LIKE';

        if ($builder->getConnection() instanceof MySqlConnection) {
            $like = $this->ignoreCase ? 'LIKE' : 'LIKE BINARY';
        }

        if ($builder->getConnection() instanceof PostgresConnection) {
            $like = $this->ignoreCase ? 'ILIKE' : 'LIKE';
        }

        $searchMethod = $searchMethod ?: SearchInput::WILDCARD;

        return match ($searchMethod) {
            SearchInput::EXACT => [$term, '='],
            SearchInput::WILDCARD => ["%{$term}%", $like],
            SearchInput::WILDCARD_LEFT => ["%{$term}", $like],
            SearchInput::WILDCARD_RIGHT => ["{$term}%", $like],
            default => throw new InvalidArgumentException("Unknown search method [{$searchMethod}]."),
        };
    }

    private function applyConstraint(array $columns, string $terms)
    {
        $terms = $this->parseTerms
            ? $this->parseTermsIntoCollection($terms)
            : Collection::wrap($terms);

        // Start with a 'where' group, loop through all terms, and
        // add an 'orWhere' contraint for each column.
        $this->builder->where(function (EloquentBuilder $builder) use ($columns, $terms) {
            $terms->each(function (string $term) use ($builder, $columns) {
                Collection::wrap($columns)->each(function (?string $searchMethod, string $column) use ($builder, $term) {
                    [$term, $whereOperator] = $this->getTermAndWhereOperator($builder, $term, $searchMethod);

                    if (! Str::contains($column, '.')) {
                        // Not a relationship, but a column on the table.
                        return $builder->orWhere($builder->qualifyColumn($column), $whereOperator, $term);
                    }

                    // Split the column into the relationship name and the key on the relationship.
                    $relation = Str::beforeLast($column, '.');
                    $key = Str::afterLast($column, '.');

                    $builder->orWhereHas($relation, function (EloquentBuilder $relation) use ($key, $whereOperator, $term) {
                        return $relation->where($relation->qualifyColumn($key), $whereOperator, $term);
                    });
                });
            });
        });
    }

    private function applySorting(Column $column)
    {
        if (is_callable($column->sortable)) {
            return ($column->sortable)($this->builder, $column->sorted);
        }

        if (! $column->isNested()) {
            // Not a relationship, just a column on the table.
            return $this->builder->orderBy($column->key, $column->sorted);
        }

        $builder = $this->builder instanceof SpatieQueryBuilder
            ? $this->builder->getEloquentBuilder()
            : $this->builder;

        if (! class_exists(EloquentJoins::class) || ! $builder instanceof EloquentBuilder || ! EloquentBuilder::hasGlobalMacro('orderByLeftPowerJoins')) {
            throw new PowerJoinsException(
                "To order the query using a column from a relationship, please install the 'kirschbaum-development/eloquent-power-joins' package."
            );
        }

        return $builder->orderByLeftPowerJoins($column->key, $column->sorted);
    }

    private function applyFilters()
    {
        $ignoreCaseSetting = $this->ignoreCase;
        $parseTermsSetting = $this->parseTerms;

        $this->parseTerms(false);

        $this->filters()->filter->hasValue()->each(function (Filter $filter) {
            if (is_callable($filter->callback)) {
                ($filter->callback)($this->builder, $filter->value);

                return;
            }

            if ($filter->isDateFilter()) {
                $this->applyDateFilter($filter);

                return;
            }

            $isTextFilter = $filter->type === 'text';

            $this->ignoreCase($isTextFilter);

            $this->applyConstraint(
                [$filter->key => $isTextFilter ? SearchInput::WILDCARD : SearchInput::EXACT],
                $filter->value
            );
        });

        $this->ignoreCase($ignoreCaseSetting);
        $this->parseTerms($parseTermsSetting);
    }

    private function applyDateFilter(Filter $filter): void
    {
        $column = $this->qualifiedColumn($filter->column ?: $filter->key);

        if ($filter->usesDateTimeBounds()) {
            $this->applyDateTimeBounds($filter, $column);

            return;
        }

        ['from' => $from, 'to' => $to] = $filter->dateRange();

        if (! is_null($from)) {
            $this->builder->whereDate($column, '>=', $from);
        }

        if (! is_null($to)) {
            $this->builder->whereDate($column, '<=', $to);
        }
    }

    private function applyDateTimeBounds(Filter $filter, string $column): void
    {
        ['from' => $from, 'until' => $until] = $filter->dateTimeBounds();

        $comparesDates = is_null($filter->timezone);

        if (! is_null($until)) {
            $comparesDates && $until->isStartOfDay()
                ? $this->builder->whereDate($column, '<', $until->toDateString())
                : $this->builder->where($column, '<', $this->storedMoment($until));
        }

        if (is_null($from)) {
            return;
        }

        if (! is_null($filter->endColumn)) {
            $endColumn = $this->qualifiedColumn($filter->endColumn);

            $this->builder->where(fn ($query) => $query
                ->where($endColumn, '>', $this->storedMoment($from))
                ->orWhereNull($endColumn));

            return;
        }

        $comparesDates && $from->isStartOfDay()
            ? $this->builder->whereDate($column, '>=', $from->toDateString())
            : $this->builder->where($column, '>=', $this->storedMoment($from));
    }

    private function storedMoment(CarbonImmutable $moment): string
    {
        return $moment
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function qualifiedColumn(string $column): string
    {
        if ($this->builder instanceof EloquentBuilder) {
            return $this->builder->qualifyColumn($column);
        }

        return $column;
    }

    private function applySearchInputs()
    {
        $this->searchInputs()->filter->value->each(
            fn (SearchInput $searchInput) => $this->applyConstraint($searchInput->columns, $searchInput->value)
        );
    }

    private function applySortingAndEagerLoading(): void
    {
        $this->applyEagerLoading();

        $anySorted = false;

        $this->columns()->each(function (Column $column) use (&$anySorted) {
            if ($column->sorted) {
                $this->applySorting($column);
                $anySorted = true;
            }
        });

        if (! $anySorted && $this->defaultSort !== '') {
            $key = ltrim($this->defaultSort, '-');
            $direction = str_starts_with($this->defaultSort, '-') ? 'desc' : 'asc';
            $this->builder->orderBy($key, $direction);
        }
    }

    private function applyEagerLoading(): void
    {
        $this->columns()
            ->filter(fn (Column $column) => $column->isNested())
            ->each(fn (Column $column) => $this->builder->with($column->relationshipName()));
    }

    public function filteredQuery(?Request $request = null): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder
    {
        return $this->derivedQuery($request, function () {
            $this->applyFilters();
            $this->applySearchInputs();
            $this->applySortingAndEagerLoading();
        });
    }

    private function derivedQuery(?Request $request, callable $apply): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder
    {
        $originalBuilder = $this->builder;
        $originalRequest = $this->request;

        $this->builder = $this->derivedBuilder($request);
        $this->request = $request ?? $originalRequest;

        try {
            if (! $this->builder instanceof SpatieQueryBuilder) {
                $apply();
            }

            return $this->builder;
        } finally {
            $this->builder = $originalBuilder;
            $this->request = $originalRequest;
        }
    }

    private function derivedBuilder(?Request $request): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder
    {
        if (! $this->builder instanceof SpatieQueryBuilder || is_null($request) || $request === $this->request || is_null($this->configurator)) {
            return clone $this->builder;
        }

        $rebuilt = $this->resolveWithRequest($request, fn () => $this->configurator->for());

        return $rebuilt instanceof SpatieQueryBuilder ? $rebuilt : clone $this->builder;
    }

    private function resolveWithRequest(Request $request, callable $resolve): mixed
    {
        $current = app('request');

        app()->instance('request', $request);

        try {
            return $resolve();
        } finally {
            app()->instance('request', $current);
        }
    }

    protected function exportItems(?Request $request): iterable
    {
        $query = $this->filteredQuery($request);

        if ($query instanceof BaseQueryBuilder) {
            return $query->cursor();
        }

        $eloquent = $query instanceof SpatieQueryBuilder ? $query->getEloquentBuilder() : $query;

        return $eloquent
            ->orderBy($eloquent->getModel()->getQualifiedKeyName())
            ->lazy(max(1, (int) config('vue-table-builder.export.chunk_size', 1000)));
    }

    protected function totalsQuery(): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder
    {
        return $this->filteredQuery();
    }

    private function loadResults(BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder $query): void
    {
        if (! $this->paginateMethod) {
            $this->resource = $query->get();

            return;
        }

        // The 'perPage' value is taken from the request query
        // string, or from the configured parameter, or it's
        // the first from the 'perPage' selector options.
        $defaultPerPage = $this->perPage ?: static::getDefaultPerPage();

        $perPage = (int) $this->query('perPage', $defaultPerPage);

        if (! in_array($perPage, $this->perPageOptions, true)) {
            $perPage = $defaultPerPage;
        }

        if (! in_array($perPage, $this->perPageOptions, true)) {
            $this->perPageOptions[] = $perPage;
        }

        sort($this->perPageOptions);

        $pageName = $this->queryKey('page');

        $this->resource = $query->{$this->paginateMethod}($perPage, ['*'], $pageName)->withQueryString();
    }

    public function addCurrentPerPageValueToOptions(): void
    {
        if ($this->perPage && ! in_array($this->perPage, $this->perPageOptions)) {
            $this->perPageOptions[] = $this->perPage;
        }
    }

    public function loadResource(): static
    {
        if ($this->resourceLoaded) {
            return $this;
        }

        if (! $this->builder instanceof SpatieQueryBuilder) {
            $this->addCurrentPerPageValueToOptions();
        }

        $this->loadResults($this->filteredQuery());

        return parent::loadResource();
    }

    public function moveRow(int|string $id, int|string $toId): void
    {
        if (! $this->isReorderable()) {
            throw new LogicException('Call reorderable() on the table before moving rows.');
        }

        if ($this->builder instanceof SpatieQueryBuilder) {
            throw new LogicException('Rows of a Spatie query builder table cannot be reordered.');
        }

        $query = clone $this->builder;

        $defaultSortColumn = ltrim($this->defaultSort, '-');

        if ($defaultSortColumn !== '' && $defaultSortColumn !== $this->reorderColumn) {
            $query->orderBy($defaultSortColumn, str_starts_with($this->defaultSort, '-') ? 'desc' : 'asc');
        }

        app(ReorderRows::class)($query, $this->reorderColumn, $this->reorderKeyName(), $id, $toId);
    }

    public function rowKeyName(): string
    {
        if ($this->primaryKey) {
            return $this->primaryKey;
        }

        $eloquent = $this->eloquentBuilder($this->builder);

        return is_null($eloquent) ? 'id' : $eloquent->getModel()->getKeyName();
    }

    private function eloquentBuilder(BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder $query): ?EloquentBuilder
    {
        if ($query instanceof SpatieQueryBuilder) {
            return $query->getEloquentBuilder();
        }

        return $query instanceof EloquentBuilder ? $query : null;
    }

    public function performBulkAction(callable $action, array $ids)
    {
        $query = $this->bulkActionQuery($ids);

        $each = function (Collection $results) use ($action) {
            $results->each($action);
        };

        $eloquent = $this->eloquentBuilder($query);

        if (is_null($eloquent)) {
            $query->reorder()->chunkById(1000, $each, $this->rowKeyName());

            return;
        }

        $eloquent->reorder()->chunkById(1000, $each);
    }

    private function bulkActionQuery(array $ids): BaseQueryBuilder|EloquentBuilder|SpatieQueryBuilder
    {
        $query = $this->derivedQuery(null, function () use ($ids) {
            $this->applyEagerLoading();

            if ($ids === ['*']) {
                $this->applyFilters();
                $this->applySearchInputs();
            }
        });

        if ($ids === ['*']) {
            return $query;
        }

        $eloquent = $this->eloquentBuilder($query);

        if (is_null($eloquent)) {
            return $query->whereIn($this->rowKeyName(), $ids);
        }

        if ($this->rowKeyName() === $eloquent->getModel()->getKeyName()) {
            return $query->whereKey($ids);
        }

        return $query->whereIn($eloquent->qualifyColumn($this->rowKeyName()), $ids);
    }
}
