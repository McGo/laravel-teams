<?php

namespace MirkoHaaser\Teams\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;

/**
 * Client-credentials token, cached until shortly before it expires. The cache
 * key carries endpoint and client id, so a config change never hits an old
 * token.
 */
trait AppTokens
{
    protected function fetchAppToken(string $tokenUrl, string $clientId, string $secret, string $scope, int $timeout, int $leeway): string
    {
        $key = 'teams:token:'.sha1($tokenUrl.'|'.$clientId.'|'.$scope);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->timeout($timeout)->post($tokenUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $secret,
            'scope' => $scope,
        ]);

        if (! $response->successful()) {
            throw new TeamsRequestFailed('Teams: app token unavailable from '.$tokenUrl, $response->status(), (string) $response->body());
        }

        $token = (string) $response->json('access_token');
        if ($token === '') {
            throw new TeamsRequestFailed('Teams: token response without access_token', $response->status());
        }

        Cache::put($key, $token, max(60, (int) ($response->json('expires_in') ?? 3600) - $leeway));

        return $token;
    }
}
