<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Kirschbaum\PowerJoins\EloquentJoins;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
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

        class_exists(EloquentJoins::class) || throw new PowerJoinsException(
            "To order the query using a column from a relationship, please install the 'kirschbaum-development/eloquent-power-joins' package."
        );

        // Apply the sorting using the PowerJoins package.
        return $this->builder->orderByLeftPowerJoins($column->key, $column->sorted);
    }

    private function applyFilters()
    {
        $ignoreCaseSetting = $this->ignoreCase;
        $parseTermsSetting = $this->parseTerms;

        $this->ignoreCase(false);
        $this->parseTerms(false);

        $this->filters()->filter->hasValue()->each(function (Filter $filter) {
            if (is_callable($filter->callback)) {
                ($filter->callback)($this->builder, $filter->value);

                return;
            }

            $this->applyConstraint([$filter->key => SearchInput::EXACT], $filter->value);
        });

        $this->ignoreCase($ignoreCaseSetting);
        $this->parseTerms($parseTermsSetting);
    }

    private function applySearchInputs()
    {
        $this->searchInputs()->filter->value->each(
            fn(SearchInput $searchInput) => $this->applyConstraint($searchInput->columns, $searchInput->value)
        );
    }

    private function applySortingAndEagerLoading(): void
    {
        $anySorted = false;

        $this->columns()->each(function (Column $column) use (&$anySorted) {
            if ($column->isNested()) {
                // Eager load the relationship.
                $this->builder->with($column->relationshipName());
            }

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

    private function loadResults()
    {
        if (! $this->paginateMethod) {
            // No pagination, so get all results.
            return $this->resource = $this->builder->get();
        }

        // The 'perPage' value is taken from the request query
        // string, or from the configured parameter, or it's
        // the first from the 'perPage' selector options.
        $defaultPerPage = $this->perPage ?: Arr::first($this->perPageOptions);

        $perPage = $this->query('perPage', $defaultPerPage);

        if (! in_array($perPage, $this->perPageOptions)) {
            // The 'perPage' value is not in the allowed options.
            // So we'll use the first option.
            $perPage = $defaultPerPage;
        }

        $this->resource = $this->builder->{$this->paginateMethod}($perPage)->withQueryString();
    }

    public function addCurrentPerPageValueToOptions(): void
    {
        if ($this->perPage && ! in_array($this->perPage, $this->perPageOptions)) {
            $this->perPageOptions[] = $this->perPage;
        }
    }

    public function loadResource(): self
    {
        if ($this->resourceLoaded) {
            return $this;
        }

        if (! $this->builder instanceof SpatieQueryBuilder) {
            $this->applyFilters();
            $this->applySearchInputs();
            $this->applySortingAndEagerLoading();
            $this->addCurrentPerPageValueToOptions();
        }

        $this->loadResults();

        return parent::loadResource();
    }

    public function performBulkAction(callable $action, array $ids)
    {
        $shouldApplyFiltersAndSearchInputs = false;

        if (! $this->builder instanceof SpatieQueryBuilder) {
            $shouldApplyFiltersAndSearchInputs = true;

            $this->applySortingAndEagerLoading();
        }

        if ($ids === ['*']) {
            if ($shouldApplyFiltersAndSearchInputs) {
                $this->applyFilters();
                $this->applySearchInputs();
            }
        } else {
            $this->builder->whereKey($ids);
        }

        $this->builder->chunkById(1000, function (Collection $results) use ($action) {
            $results->each($action);
        });
    }
}
