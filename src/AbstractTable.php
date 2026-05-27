<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use TranquilTools\TableBuilder\Components\BulkAction;

abstract class AbstractTable
{
    private ?TableBuilder $table = null;

    public function authorize(Request $request): bool
    {
        return true;
    }

    /**
     * @return Builder|Relation|Model|Collection|array|string
     */
    public function for(): mixed
    {
        return [];
    }

    public static function build(...$arguments): TableBuilder
    {
        $table = new static(...$arguments);

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

    public function configure(TableBuilder $table)
    {
        //
    }

    public function performBulkAction(int $key, array $ids)
    {
        $table = $this->make();

        if ($table instanceof QueryBuilder) {
            /** @var BulkAction $bulkAction */
            $bulkAction = $table->getBulkActions()[$key];

            if ($bulkAction->beforeCallback) {
                call_user_func($bulkAction->beforeCallback, $ids);
            }

            if ($bulkAction->eachCallback) {
                $table->performBulkAction($bulkAction->eachCallback, $ids);
            }

            if ($bulkAction->afterCallback) {
                call_user_func($bulkAction->afterCallback, $ids);
            }
        }
    }
}
