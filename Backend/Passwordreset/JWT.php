<?php
// Backend/Passwordreset/JWT.php
// Pure PHP JWT implementation.

class JWT
{
    private static function getSecret(): string
    {
        $cfg = require __DIR__ . '/../config.php';
        return $cfg['jwt_secret'];
    }

    // ── Encode ────────────────────────────────────────────────────────────────
    public static function encode(array $payload, int $expirySeconds = 3600): string
    {
        $header  = self::base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['exp'] = time() + $expirySeconds;
        $payload['iat'] = time();
        $payload = self::base64url(json_encode($payload));

        $signature = self::base64url(
            hash_hmac('sha256', "$header.$payload", self::getSecret(), true)
        );

        return "$header.$payload.$signature";
    }

    // ── Decode & Verify ───────────────────────────────────────────────────────
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new Exception('Invalid token structure.');
        }

        [$header, $payload, $signature] = $parts;

        $expectedSig = self::base64url(
            hash_hmac('sha256', "$header.$payload", self::getSecret(), true)
        );

        if (!hash_equals($expectedSig, $signature)) {
            throw new Exception('Invalid token signature.');
        }

        $data = json_decode(self::base64urlDecode($payload), true);

        if (!$data) {
            throw new Exception('Could not decode token payload.');
        }

        if (!isset($data['exp']) || time() > $data['exp']) {
            throw new Exception('Token has expired.');
        }

        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
