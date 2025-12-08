<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\UnauthorizedException;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\PasswordValidator;
use TranquilTools\TableBuilder\Components\BulkAction;

class TableBulkActionController extends Controller
{
    public function __invoke(Request $request, $table, $action, PasswordValidator $passwordValidator): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
        ]);

        $action = base64_decode($action);

        /** @var AbstractTable $tableInstance */
        $tableInstance = app(base64_decode($table));

        if (!$tableInstance->authorize($request)) {
            throw new UnauthorizedException;
        }

        /** @var BulkAction */
        $bulkActionInstance = $tableInstance->make()->getBulkActions()[$action];

        if ($bulkActionInstance->requirePassword) {
            $passwordValidator->validateRequest($request, $bulkActionInstance->requirePassword);
        }

        $tableInstance->performBulkAction($action, $request->input('ids', []));

        return redirect()->back();
    }
}