<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Contracts;

interface AuthenticatorInterface
{
    /**
     * Get a valid access token, refreshing if necessary.
     */
    public function getAccessToken(): string;

    /**
     * Force refresh of the access token.
     */
    public function refreshToken(): string;

    /**
     * Clear any cached tokens.
     */
    public function clearToken(): void;
}
