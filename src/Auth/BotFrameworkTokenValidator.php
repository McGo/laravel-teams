<?php

namespace MirkoHaaser\Teams\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Exceptions\TeamsTokenInvalid;
use Throwable;

/**
 * Validates the token the Bot Connector sends with every activity: signature
 * against the keys from the Bot Framework OpenID metadata, issuer, audience
 * (the bot app id), serviceUrl and, where the key carries endorsements, the
 * msteams endorsement.
 */
class BotFrameworkTokenValidator
{
    public const OPENID = 'https://login.botframework.com/v1/.well-known/openidconfiguration';

    public const ISSUER = 'https://api.botframework.com';

    private const CACHE_TTL = 86400;

    private const LEEWAY = 300;

    public function __construct(private readonly string $botAppId) {}

    /**
     * @return array<string, mixed>
     *
     * @throws TeamsTokenInvalid
     */
    public function validate(string $token, string $serviceUrl): array
    {
        if ($this->botAppId === '') {
            throw new TeamsTokenInvalid('No bot app id configured');
        }

        $jwks = $this->jwks();

        try {
            JWT::$leeway = self::LEEWAY;
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
        } catch (Throwable $e) {
            throw new TeamsTokenInvalid('Bot token invalid: '.$e->getMessage());
        }

        if ((string) ($claims['iss'] ?? '') !== self::ISSUER) {
            throw new TeamsTokenInvalid('Unexpected issuer');
        }

        if ((string) ($claims['aud'] ?? '') !== $this->botAppId) {
            throw new TeamsTokenInvalid('Token is meant for another bot');
        }

        // Not every token carries the claim. If it does, it has to match.
        $tokenServiceUrl = (string) ($claims['serviceurl'] ?? $claims['serviceUrl'] ?? '');
        if ($tokenServiceUrl !== '' && rtrim($tokenServiceUrl, '/') !== rtrim($serviceUrl, '/')) {
            throw new TeamsTokenInvalid('serviceUrl does not match the token');
        }

        $this->assertEndorsement($token, $jwks);

        return $claims;
    }

    /**
     * Keys that list endorsements must list msteams. Keys without the field
     * are accepted; signature, issuer and audience carry the authentication.
     *
     * @param  array<string, mixed>  $jwks
     */
    private function assertEndorsement(string $token, array $jwks): void
    {
        $kid = $this->kid($token);

        foreach ((array) ($jwks['keys'] ?? []) as $key) {
            if (($key['kid'] ?? null) !== $kid || ! isset($key['endorsements'])) {
                continue;
            }

            if (! in_array('msteams', (array) $key['endorsements'], true)) {
                throw new TeamsTokenInvalid('Signing key is not endorsed for msteams');
            }
        }
    }

    private function kid(string $token): ?string
    {
        $header = json_decode((string) base64_decode(strtr(explode('.', $token)[0], '-_', '+/'), true), true);

        return is_array($header) ? ($header['kid'] ?? null) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(): array
    {
        return Cache::remember('teams:bot_jwks', self::CACHE_TTL, function (): array {
            $config = Http::timeout(10)->get(self::OPENID);
            if (! $config->successful()) {
                throw new TeamsTokenInvalid('Bot Framework OpenID metadata unavailable');
            }

            $keys = Http::timeout(10)->get((string) $config->json('jwks_uri'));
            if (! $keys->successful()) {
                throw new TeamsTokenInvalid('Bot Framework signing keys unavailable');
            }

            return (array) $keys->json();
        });
    }
}
