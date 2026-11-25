<?php

namespace MirkoHaaser\Teams\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Exceptions\TeamsTokenInvalid;
use Throwable;

/**
 * Validates the SSO token a tab gets from TeamsJS: tenant on the allow list,
 * signature against the tenant's keys, audience, issuer, lifetime.
 *
 * Keys are cached for a day. Microsoft rotates them, and a key set held too
 * long breaks sign-in without a code change.
 */
class EntraTokenValidator
{
    private const CACHE_TTL = 86400;

    private const LEEWAY = 300;

    /**
     * @param  list<string>  $allowedTenants
     */
    public function __construct(
        private readonly string $clientId,
        private readonly array $allowedTenants,
        private readonly string $loginBaseUrl,
        private readonly ?string $appIdUri = null,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws TeamsTokenInvalid
     */
    public function validate(string $token): array
    {
        $tenantId = $this->tenantFromToken($token);

        if (! in_array($tenantId, $this->allowedTenants, true)) {
            throw new TeamsTokenInvalid('Tenant not allowed');
        }

        try {
            JWT::$leeway = self::LEEWAY;
            $claims = (array) JWT::decode($token, JWK::parseKeySet($this->jwks($tenantId), 'RS256'));
        } catch (Throwable $e) {
            throw new TeamsTokenInvalid('Signature or lifetime invalid: '.$e->getMessage());
        }

        $audiences = array_values(array_filter([$this->clientId, $this->appIdUri]));
        if (! in_array((string) ($claims['aud'] ?? ''), $audiences, true)) {
            throw new TeamsTokenInvalid('Token is meant for another audience');
        }

        if ((string) ($claims['iss'] ?? '') !== "{$this->loginBaseUrl}/{$tenantId}/v2.0") {
            throw new TeamsTokenInvalid('Unexpected issuer (v2 token required)');
        }

        if ((string) ($claims['tid'] ?? '') !== $tenantId) {
            throw new TeamsTokenInvalid('Tenant claim changed');
        }

        return $claims;
    }

    /**
     * The tenant decides which keys to check against, so it is read before
     * the signature and held against the allow list first.
     */
    private function tenantFromToken(string $token): string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new TeamsTokenInvalid('Not a JWT');
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $tid = is_array($payload) ? (string) ($payload['tid'] ?? '') : '';

        if ($tid === '') {
            throw new TeamsTokenInvalid('No tid claim');
        }

        return $tid;
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(string $tenantId): array
    {
        return Cache::remember("teams:jwks:{$tenantId}", self::CACHE_TTL, function () use ($tenantId): array {
            $response = Http::timeout(10)->get("{$this->loginBaseUrl}/{$tenantId}/discovery/v2.0/keys");

            if (! $response->successful()) {
                throw new TeamsTokenInvalid('Signing keys unavailable');
            }

            return (array) $response->json();
        });
    }
}
