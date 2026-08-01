<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class HalaxyException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $operationOutcome
     */
    public function __construct(
        string $message,
        protected ?int $statusCode = null,
        protected ?string $requestMethod = null,
        protected ?string $requestUrl = null,
        protected ?array $operationOutcome = null,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
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
        $message = self::extractMessage($body) ?? 'An unknown error occurred';

        return new self(
            message: $message,
            statusCode: $response->status(),
            requestMethod: $method,
            requestUrl: $url,
            operationOutcome: $body,
        );
    }

    /**
     * Extract error message from FHIR OperationOutcome.
     *
     * @param  array<string, mixed>|null  $body
     */
    protected static function extractMessage(?array $body): ?string
    {
        if (! $body) {
            return null;
        }

        // Try to extract from FHIR OperationOutcome
        if (isset($body['resourceType']) && $body['resourceType'] === 'OperationOutcome') {
            $issue = $body['issue'][0] ?? null;
            if ($issue) {
                return $issue['diagnostics'] ?? $issue['details']['text'] ?? null;
            }
        }

        // Try common error message fields
        return $body['message'] ?? $body['error'] ?? $body['error_description'] ?? null;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getRequestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOperationOutcome(): ?array
    {
        return $this->operationOutcome;
    }
}
