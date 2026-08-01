<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

final class RateLimitException extends HalaxyException
{
    public function __construct(
        string $message = 'Rate limit exceeded. Please slow down your requests.',
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct(message: $message, statusCode: 429);
    }

    public static function withRetryAfter(int $seconds): self
    {
        return new self(
            message: "Rate limit exceeded. Please retry after {$seconds} seconds.",
            retryAfter: $seconds,
        );
    }
}
