<?php

namespace App\Support;

use RuntimeException;

class Jwt
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public static function encode(array $claims, ?string $secret = null, int $ttlSeconds = 900): string
    {
        $secret = self::secret($secret);
        $now = time();
        $payload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
        ], $claims);

        $header = self::b64(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = self::b64($payload);
        $signature = self::b64raw(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $token, ?string $secret = null): array
    {
        $secret = self::secret($secret);
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token');
        }

        [$header, $body, $signature] = $parts;
        $expected = self::b64raw(hash_hmac('sha256', $header.'.'.$body, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        $payload = json_decode(self::ub64($body), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid token payload');
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            throw new RuntimeException('Token expired');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function b64(array $value): string
    {
        return self::b64raw(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private static function b64raw(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function ub64(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }

    private static function secret(?string $secret): string
    {
        $secret = $secret ?: (string) config('jwt.secret');
        $secret = $secret ?: (string) config('app.key');
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured');
        }

        return $secret;
    }
}
