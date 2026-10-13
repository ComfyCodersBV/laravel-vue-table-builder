<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\Components\BulkAction;
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

        $choice = $this->validatedChoice($request, $table->make()->getBulkActions()[$key]);

        $ids = $request->input('ids', []);

        if ($ids === ['*']) {
            $this->applyPostedTableState($request, $table);
        }

        $table->withBulkActionChoice($choice)->performBulkAction($key, $ids);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'performed' => true,
            ]);
        }

        return redirect()->back();
    }

    private function validatedChoice(BulkActionRequest $request, BulkAction $bulkAction): ?string
    {
        if (! $bulkAction->hasChoices()) {
            return null;
        }

        $validated = $request->validate([
            'choice' => [
                'required',
                Rule::in($bulkAction->choiceValues()),
            ],
        ]);

        return (string) $validated['choice'];
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
