<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use TranquilTools\TableBuilder\Http\Controllers\TableBulkActionController;

Route::post('/table/bulk-action', TableBulkActionController::class)
    ->middleware(ValidateSignature::class)
    ->name('table.bulk-action');
