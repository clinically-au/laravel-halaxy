<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

final class ServerException extends HalaxyException
{
    public static function internalError(): self
    {
        return new self(
            message: 'The Halaxy API encountered an internal error. Please try again later.',
            statusCode: 500,
        );
    }

    public static function serviceUnavailable(): self
    {
        return new self(
            message: 'The Halaxy API is temporarily unavailable. Please try again later.',
            statusCode: 503,
        );
    }
}
