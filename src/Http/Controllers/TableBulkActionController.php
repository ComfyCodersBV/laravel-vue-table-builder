<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use TranquilTools\TableBuilder\Http\Requests\BulkActionRequest;
use TranquilTools\TableBuilder\Routing\TableResolver;

class TableBulkActionController extends Controller
{
    public function __invoke(BulkActionRequest $request): RedirectResponse|JsonResponse
    {
        $table = TableResolver::fromRequest($request);

        $key = (int) base64_decode((string) $request->query('action'));

        if (! $table->authorizeBulkAction($request, $key)) {
            throw new AuthorizationException;
        }

        if (! array_key_exists($key, $table->make()->getBulkActions())) {
            abort(404);
        }

        $table->performBulkAction($key, $request->input('ids', []));

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'performed' => true,
            ]);
        }

        return redirect()->back();
    }
}
