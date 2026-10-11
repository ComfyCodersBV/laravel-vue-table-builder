<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use TranquilTools\TableBuilder\Http\Controllers\TableBulkActionController;
use TranquilTools\TableBuilder\Http\Controllers\TableReorderController;

Route::post('/table/bulk-action', TableBulkActionController::class)
    ->middleware(ValidateSignature::class)
    ->name('table.bulk-action');

Route::post('/table/reorder', TableReorderController::class)
    ->middleware(ValidateSignature::class)
    ->name('table.reorder');
