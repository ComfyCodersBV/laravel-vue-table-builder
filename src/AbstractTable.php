<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use ReflectionClass;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TranquilTools\TableBuilder\Components\BulkAction;

abstract class AbstractTable
{
    private ?TableBuilder $table = null;

    private ?string $bulkActionChoice = null;

    public function authorize(Request $request): bool
    {
        return true;
    }

    public function authorizeBulkAction(Request $request, int $key): bool
    {
        return $this->authorize($request);
    }

    public function authorizeReorder(Request $request): bool
    {
        return $this->authorize($request);
    }

    public function authorizeExport(Request $request): bool
    {
        return $this->authorize($request);
    }

    /**
     * @return array<string, int|string|float|bool|null>
     */
    public function signedParameters(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function fromSignedParameters(array $parameters, Request $request): static
    {
        return app(static::class, $parameters);
    }

    /**
     * @return Builder|BaseQueryBuilder|SpatieQueryBuilder|Relation|Model|Collection|array|string|LengthAwarePaginator
     */
    public function for()
    {
        return [];
    }

    public static function build(...$arguments): TableBuilder
    {
        $table = (new ReflectionClass(static::class))->newInstanceArgs($arguments);

        return $table->make()->beforeRender();
    }

    public function make(): TableBuilder
    {
        if ($this->table) {
            return $this->table;
        }

        return $this->table = tap(
            TableBuilder::for($this->for()),
            function (TableBuilder $table) {
                $table->setConfigurator($this);
                $this->configure($table);
            }
        );
    }

    public function refresh(): TableBuilder
    {
        $this->table = null;

        return $this->make();
    }

    public function configure(TableBuilder $table)
    {
        //
    }

    public function filteredQuery(?Request $request = null): Builder|BaseQueryBuilder|SpatieQueryBuilder
    {
        return $this->make()->filteredQuery($request);
    }

    public function export(?Request $request = null): StreamedResponse
    {
        return $this->make()->export($request);
    }

    public function performReorder(int|string $id, int|string $toId): void
    {
        $this->make()->moveRow($id, $toId);
    }

    public function withBulkActionChoice(?string $choice): static
    {
        $this->bulkActionChoice = $choice;

        return $this;
    }

    public function bulkActionChoice(): ?string
    {
        return $this->bulkActionChoice;
    }

    public function performBulkAction(int $key, array $ids)
    {
        $table = $this->make();

        if (! $table instanceof QueryBuilder) {
            return;
        }

        /** @var BulkAction $bulkAction */
        $bulkAction = $table->getBulkActions()[$key];

        $arguments = $bulkAction->hasChoices() ? [$ids, $this->bulkActionChoice] : [$ids];

        if ($bulkAction->beforeCallback) {
            call_user_func($bulkAction->beforeCallback, ...$arguments);
        }

        if ($bulkAction->eachCallback) {
            $table->performBulkAction($this->eachCallback($bulkAction), $ids);
        }

        if ($bulkAction->afterCallback) {
            call_user_func($bulkAction->afterCallback, ...$arguments);
        }
    }

    private function eachCallback(BulkAction $bulkAction): Closure
    {
        $each = $bulkAction->eachCallback;

        if (! $bulkAction->hasChoices()) {
            return $each;
        }

        $choice = $this->bulkActionChoice;

        return fn (mixed $row): mixed => $each($row, $choice);
    }
}
