<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TranquilTools\TableBuilder\TableBuilder
 */
class TableBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TranquilTools\TableBuilder\TableBuilder::class;
    }
}
