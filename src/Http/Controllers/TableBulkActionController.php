<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use TranquilTools\TableBuilder\AbstractTable;
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

        $ids = $request->input('ids', []);

        if ($ids === ['*']) {
            $this->applyPostedTableState($request, $table);
        }

        $table->performBulkAction($key, $ids);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'performed' => true,
            ]);
        }

        return redirect()->back();
    }

    private function applyPostedTableState(BulkActionRequest $request, AbstractTable $table): void
    {
        if (! $request->has('state')) {
            return;
        }

        parse_str(ltrim((string) $request->input('state'), '?'), $posted);

        $stateKeys = array_values(Arr::only($table->make()->queryKeys(), [
            'sort',
            'filter',
        ]));

        foreach ([$request, request()] as $target) {
            $target->query->replace(array_merge(
                Arr::except($target->query->all(), $stateKeys),
                Arr::only($posted, $stateKeys),
            ));
        }

        $table->refresh();
    }
}
