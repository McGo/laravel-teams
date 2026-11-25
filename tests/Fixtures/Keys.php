<?php

namespace MirkoHaaser\Teams\Tests\Fixtures;

use Firebase\JWT\JWT;

/** A throwaway RSA key pair and the matching JWKS, generated once per run. */
final class Keys
{
    private static ?string $private = null;

    /** @var array<string, mixed>|null */
    private static ?array $jwk = null;

    public const KID = 'test-key';

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function sign(array $claims, string $kid = self::KID): string
    {
        self::ensure();

        return JWT::encode($claims + ['iat' => time(), 'nbf' => time() - 10, 'exp' => time() + 3600], (string) self::$private, 'RS256', $kid);
    }

    /**
     * @param  list<string>|null  $endorsements
     * @return array<string, mixed>
     */
    public static function jwks(?array $endorsements = null): array
    {
        self::ensure();
        $key = self::$jwk;
        if ($endorsements !== null) {
            $key['endorsements'] = $endorsements;
        }

        return ['keys' => [$key]];
    }

    private static function ensure(): void
    {
        if (self::$private !== null) {
            return;
        }

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $private);
        $details = openssl_pkey_get_details($res);

        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        self::$private = $private;
        self::$jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::KID,
            'n' => $b64($details['rsa']['n']),
            'e' => $b64($details['rsa']['e']),
        ];
    }
}
