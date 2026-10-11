<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ValidateExportSignature
{
    public const SIGNED_PARAMETERS = [
        'table',
        'params',
        'expires',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ignoresTableState = fn (string $parameter): bool => ! in_array($parameter, self::SIGNED_PARAMETERS, true);

        if (! URL::hasValidSignature($request, true, $ignoresTableState)) {
            throw new InvalidSignatureException;
        }

        return $next($request);
    }
}
