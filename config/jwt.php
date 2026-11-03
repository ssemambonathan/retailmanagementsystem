<?php

declare(strict_types=1);

return [

    /*
    |──────────────────────────────────────────────────────────────────────────
    | JWT Authentication Secret
    |──────────────────────────────────────────────────────────────────────────
    | Generated via: php artisan jwt:secret
    | In production: store in AWS Secrets Manager, not .env
    */
    'secret' => env('JWT_SECRET'),

    'keys' => [
        'public'     => env('JWT_PUBLIC_KEY'),
        'private'    => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Token Lifetimes
    |──────────────────────────────────────────────────────────────────────────
    | ttl         : Access token TTL in MINUTES (15 min — short for security)
    | refresh_ttl : How long (minutes) to allow refresh. Not used for our
    |               custom refresh tokens — that's in RefreshToken model.
    */
    'ttl'         => (int) env('JWT_TTL', 15),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160), // 14 days (fallback)

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Required Claims
    |──────────────────────────────────────────────────────────────────────────
    | These claims MUST be present in every token or it is rejected.
    | 'jti' : unique token ID — used for blacklisting
    | 'ver' : token version — invalidates on password change
    */
    'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub', 'jti'],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Persistent Claims
    |──────────────────────────────────────────────────────────────────────────
    | Claims preserved across token refresh (tymon's built-in refresh).
    | We use our own rotation, but keep these for compatibility.
    */
    'persistent_claims' => ['role', 'ver'],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Lock Subject
    |──────────────────────────────────────────────────────────────────────────
    | Enforces that the same user must be authenticated when refreshing.
    | Prevents token substitution attacks.
    */
    'lock_subject' => true,

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Leeway
    |──────────────────────────────────────────────────────────────────────────
    | Seconds of clock skew tolerance. Keep at 0 in production.
    | Only increase if you have distributed systems with clock drift.
    */
    'leeway' => (int) env('JWT_LEEWAY', 0),

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Blacklist
    |──────────────────────────────────────────────────────────────────────────
    | We manage our own blacklist via JwtBlacklist model + Redis cache.
    | Disable tymon's built-in blacklist to avoid double-processing.
    */
    'blacklist_enabled'      => (bool) env('JWT_BLACKLIST_ENABLED', false),
    'blacklist_grace_period' => (int) env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    'decrypt_cookies' => false,

    'providers' => [
        'jwt'     => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth'    => Tymon\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,
    ],

];
