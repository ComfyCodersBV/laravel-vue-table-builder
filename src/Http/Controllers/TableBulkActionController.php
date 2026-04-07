<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use TranquilTools\TableBuilder\Http\Requests\BulkActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\UnauthorizedException;
use TranquilTools\TableBuilder\AbstractTable;

class TableBulkActionController extends Controller
{
    public function __invoke(BulkActionRequest $request): RedirectResponse
    {
        $action = base64_decode($request->input('action'));

        /** @var AbstractTable $tableInstance */
        $tableInstance = app(base64_decode($request->input('table')));

        if (! $tableInstance->authorize($request)) {
            throw new UnauthorizedException;
        }

        $tableInstance->performBulkAction((int) $action, $request->input('ids', []));

        return redirect()->back();
    }
}