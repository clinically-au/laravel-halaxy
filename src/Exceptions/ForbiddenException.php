<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

final class ForbiddenException extends HalaxyException
{
    public static function insufficientPermissions(): self
    {
        return new self(
            message: 'You do not have permission to perform this action.',
            statusCode: 403,
        );
    }
}
