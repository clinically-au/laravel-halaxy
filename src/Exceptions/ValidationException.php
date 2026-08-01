<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

final class ValidationException extends HalaxyException
{
    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    public static function withErrors(array $errors): self
    {
        $messages = array_map(
            fn (array $error): string => $error['diagnostics'] ?? $error['details']['text'] ?? 'Validation error',
            $errors,
        );

        return new self(
            message: implode('; ', $messages),
            statusCode: 422,
            operationOutcome: ['issue' => $errors],
        );
    }
}
