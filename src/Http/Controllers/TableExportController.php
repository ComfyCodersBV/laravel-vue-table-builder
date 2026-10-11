<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TranquilTools\TableBuilder\Routing\TableResolver;

class TableExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $table = TableResolver::fromRequest($request);

        if (! $table->authorizeExport($request)) {
            throw new AuthorizationException;
        }

        $builder = $table->make();

        if (! $builder->isExportable()) {
            abort(404);
        }

        return $builder->export($request);
    }
}
