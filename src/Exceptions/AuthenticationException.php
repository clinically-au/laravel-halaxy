<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

final class AuthenticationException extends HalaxyException
{
    public static function invalidCredentials(?string $detail = null): self
    {
        return new self(
            message: 'Invalid client credentials. Please check your HALAXY_CLIENT_ID and HALAXY_CLIENT_SECRET.'
                .($detail !== null && $detail !== '' ? " ({$detail})" : ''),
            statusCode: 401,
        );
    }

    public static function tokenExpired(): self
    {
        return new self(
            message: 'Access token has expired. Please refresh the token.',
            statusCode: 401,
        );
    }

    public static function missingCredentials(): self
    {
        return new self(
            message: 'Missing API credentials. Please configure HALAXY_CLIENT_ID and HALAXY_CLIENT_SECRET.',
            statusCode: 401,
        );
    }
}
