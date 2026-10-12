<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Tests\Fixtures\PhpStan;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;
use TranquilTools\TableBuilder\Tests\Fixtures\Product;

class SpatieProductsTable extends AbstractTable
{
    /**
     * @return QueryBuilder<Product>
     */
    public function for(): QueryBuilder
    {
        return QueryBuilder::for(Product::class)
            ->allowedFilters(AllowedFilter::exact('sku'))
            ->allowedSorts('name');
    }

    public function configure(TableBuilder $table): void
    {
        $table->column('name', sortable: true);
    }
}
