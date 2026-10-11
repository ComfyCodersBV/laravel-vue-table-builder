<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Http\Requests\ReorderRequest;

class TableReorderController extends Controller
{
    public function __invoke(ReorderRequest $request): RedirectResponse|JsonResponse
    {
        $tableClass = base64_decode((string) $request->query('table'));

        if (! is_subclass_of($tableClass, AbstractTable::class)) {
            abort(404);
        }

        /** @var AbstractTable $tableInstance */
        $tableInstance = app($tableClass);

        if (! $tableInstance->authorize($request)) {
            throw new UnauthorizedException;
        }

        try {
            $tableInstance->performReorder($request->rowId(), $request->targetId());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->getMessage(),
            ]);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'reordered' => true,
            ]);
        }

        return redirect()->back();
    }
}
