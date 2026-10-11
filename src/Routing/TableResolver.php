<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Routing;

use Illuminate\Http\Request;
use TranquilTools\TableBuilder\AbstractTable;

final class TableResolver
{
    public static function fromRequest(Request $request): AbstractTable
    {
        $tableClass = base64_decode((string) $request->query('table'), true);

        if (! is_string($tableClass) || ! is_subclass_of($tableClass, AbstractTable::class)) {
            abort(404);
        }

        $parameters = self::parameters($request);

        if (is_null($parameters)) {
            abort(404);
        }

        return $tableClass::fromSignedParameters($parameters, $request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parameters(Request $request): ?array
    {
        $encoded = $request->query('params');

        if (is_null($encoded)) {
            return [];
        }

        if (! is_string($encoded)) {
            return null;
        }

        return SignedTableUrl::decodeParameters($encoded);
    }
}
