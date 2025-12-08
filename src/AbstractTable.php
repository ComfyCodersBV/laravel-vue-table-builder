<?php

namespace TranquilTools\TableBuilder;

use Illuminate\Http\Request;
use ProtoneMedia\Splade\Table\BulkAction;
use ProtoneMedia\Splade\Table\Export;

abstract class AbstractTable
{
    /**
     * The TableBuilder instance.
     */
    private ?TableBuilder $table = null;

    /**
     * Determine if the user is authorized to perform bulk actions and exports.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        return true;
    }

    /**
     * The resource or query builder.
     *
     * @return mixed
     */
    public function for()
    {
        return [];
    }

    /**
     * Helper method to create a new TableBuilder instance.
     */
    public static function build(...$arguments): TableBuilder
    {
        $table = new static(...$arguments);

        return $table->make()->beforeRender();
    }

    /**
     * Creates a new TableBuilder instance with the resource or
     * query builder from the 'for()' method of this class.
     */
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

    /**
     * Configure the given TableBuilder.
     *
     * @return void
     */
    public function configure(TableBuilder $table)
    {
        //
    }

    /**
     * Returns a TableExporter instance.
     */
    public function makeExporter(int $key): ?TableExporter
    {
        $table = $this->make();

        if (! $table instanceof QueryBuilder) {
            return null;
        }

        /** @var Export $export */
        $export = $table->getExports()[$key];

        return new TableExporter(
            $table,
            $export->filename,
            $export->type,
            $export->events
        );
    }

    /**
     * Performs the bulk action on the given ids.
     *
     * @return void
     */
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