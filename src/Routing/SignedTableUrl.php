<?php

declare(strict_types=1);

namespace TranquilTools\TableBuilder\Routing;

use Illuminate\Support\Facades\URL;

final class SignedTableUrl
{
    /**
     * @param  class-string  $tableClass
     * @param  array<string, mixed>  $tableParameters
     * @param  array<string, mixed>  $parameters
     */
    public static function make(string $routeName, string $tableClass, array $tableParameters = [], array $parameters = []): string
    {
        $signed = array_merge($parameters, [
            'table' => base64_encode($tableClass),
        ]);

        if ($tableParameters !== []) {
            $signed['params'] = self::encodeParameters($tableParameters);
        }

        $ttl = (int) config('vue-table-builder.routes.signature_ttl', 0);

        if ($ttl <= 0) {
            return URL::signedRoute($routeName, $signed);
        }

        return URL::temporarySignedRoute($routeName, now()->addMinutes($ttl), $signed);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function encodeParameters(array $parameters): string
    {
        return base64_encode((string) json_encode($parameters, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeParameters(string $encoded): ?array
    {
        $json = base64_decode($encoded, true);

        if ($json === false) {
            return null;
        }

        $parameters = json_decode($json, true);

        return is_array($parameters) ? $parameters : null;
    }
}
