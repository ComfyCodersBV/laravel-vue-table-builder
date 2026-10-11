<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use TranquilTools\TableBuilder\Http\Requests\ReorderRequest;
use TranquilTools\TableBuilder\Routing\TableResolver;

class TableReorderController extends Controller
{
    public function __invoke(ReorderRequest $request): RedirectResponse|JsonResponse
    {
        $table = TableResolver::fromRequest($request);

        if (! $table->authorizeReorder($request)) {
            throw new AuthorizationException;
        }

        if (! $table->make()->isReorderable()) {
            abort(404);
        }

        try {
            $table->performReorder($request->rowId(), $request->targetId());
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
