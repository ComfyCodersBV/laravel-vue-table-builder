<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Tests\Fixtures\PhpStan;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use TranquilTools\TableBuilder\AbstractTable;

class DatabaseQueryTable extends AbstractTable
{
    public function for(): Builder
    {
        return DB::table('products');
    }
}
