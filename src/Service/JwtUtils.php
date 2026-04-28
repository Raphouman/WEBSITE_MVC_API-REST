<?php
// namespace App\Models\Utils;
// use DateTime;
// use DateInterval;
// use RuntimeException;

// DateTime, DateInterval, RuntimeException sont des classes PHP globales,
// elles fonctionnent sans use dès qu'on n'est plus dans un namespace.

class JwtUtils
{
    private const SECRET = 'eX4SsAZhyB5klpIotUN0AA09KPtOV32B'; // base64_encode(random_bytes(24));

    public const DURATION = 'PT2H'; //DateInterval format: PT2H, PT20S

    public const STATUS_VALID = 1;
    public const STATUS_INVALID = -1;
    public const STATUS_EXPIRED = -2;

    /**
     * See documentation: https://www.rfc-editor.org/rfc/rfc7519
     * @param array $data information added to JWT payload
     * 
     * @return array
     */
    public static function newAccessToken(array $data = []): array
    {
        $now = new DateTime();
        $expires = (clone $now)->add(new DateInterval(self::DURATION));

        $header_encoded = self::base64url_encode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));
        $payload_data = array_merge([
            'token' => base64_encode(random_bytes(12)),
            'iat' => $now->format('U'),
            'exp' => $expires->format('U')
        ], $data);
        $payload_encoded = self::base64url_encode(json_encode($payload_data));
        $access_token = sprintf(
            '%s.%s.%s',
            $header_encoded,
            $payload_encoded,
            self::buildSignature($header_encoded, $payload_encoded)
        );

        return [
            'access_token' => $access_token,
            'issued' => $now->format('c'),
            'expires' => $expires->format('c')
        ];
    }

    protected static function buildSignature($header, $payload): string
    {
        $header_and_payload = sprintf(
            '%s.%s',
            $header,
            $payload
        );
        return self::base64url_encode(hash_hmac('sha256', $header_and_payload, base64_decode(self::SECRET), true));
    }

    /**
     * Format a string in base64 respecting the URL encoded variant (uses - instead of + and _ instead of / and doesn't use = for padding)
     * @param string $data JSON string
     */
    protected static function base64url_encode(string $data): string
    {
        $base64 = base64_encode($data);
        if ($base64 === false)
            return false;
        return rtrim(strtr($base64, '+/', '-_'), '=');
    }

    protected static function base64url_decode(string $base64): string
    {
        $base64 = strtr($base64, '-_', '+/');
        // TODO: add some '='?
        $data = base64_decode($base64);
        if ($data === false)
            return false;
        return $data;
    }

    public static function getAccessTokenFromRequest(): string
    {
        $request_headers=array_change_key_case(getallheaders(), CASE_LOWER);
        if (isset($request_headers['authorization']) === false) {
            throw new RuntimeException('Authorization header is not valid');
        }

        $header_arguments = preg_split('/\s+/', $request_headers['authorization']);
        if (count($header_arguments) !== 2 || $header_arguments[0] !== 'Bearer') {
            throw new RuntimeException('Authorization header is not valid');
        }

        $access_token = $header_arguments[1];
        return $access_token;
    }

    /**
     * Control a given JWT for validity and expiration
     * @param string $jwt
     * 
     * @return int one of the ::STATUS_XXX constants
     */
    public static function checkAccessToken(string $access_token): int
    {
        $parts = explode('.', $access_token);
        if (count($parts) != 3)
            return self::STATUS_INVALID;
        
        $header_json = self::base64url_decode($parts[0]);
        $payload_json = self::base64url_decode($parts[1]);
        $payload = json_decode($payload_json);
        $signature_provided = $parts[2];

        $signature_computed = self::buildSignature(self::base64url_encode($header_json), self::base64url_encode($payload_json));

        // var_dump($header, $payload, $signature_provided, $signature_computed);
        if ($signature_provided === $signature_computed) {
            $expiration = DateTime::createFromFormat('U', $payload->exp);

            if ($expiration > new DateTime())
                return self::STATUS_VALID;
            
            return self::STATUS_EXPIRED;
        }

        var_dump($signature_provided, $signature_computed, $expiration);

        return self::STATUS_INVALID;
    }

    /**
     * Get payload data from JWT as object
     * @param string $jwt
     * 
     * @return object
     */
    public static function getPayload(string $jwt): object
    {
        $parts = explode('.', $jwt);
        $payload_json = self::base64url_decode($parts[1]);
        $payload = json_decode($payload_json);
        return $payload;
    }
}
