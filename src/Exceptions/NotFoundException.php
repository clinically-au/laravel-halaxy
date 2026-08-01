<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

use Illuminate\Http\Client\Response;

final class NotFoundException extends HalaxyException
{
    public static function resource(string $resourceType, string $id): self
    {
        return new self(
            message: "{$resourceType} with ID '{$id}' was not found.",
            statusCode: 404,
        );
    }

    /**
     * Create an exception from an HTTP response.
     */
    public static function fromResponse(
        Response $response,
        ?string $method = null,
        ?string $url = null,
    ): self {
        $body = $response->json();
        $message = self::extractMessage($body) ?? 'Resource not found';

        return new self(
            message: $message,
            statusCode: $response->status(),
            requestMethod: $method,
            requestUrl: $url,
            operationOutcome: $body,
        );
    }
}
