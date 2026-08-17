<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Http;

use Clinically\Halaxy\Contracts\AuthenticatorInterface;
use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Exceptions\AuthenticationException;
use Clinically\Halaxy\Exceptions\ForbiddenException;
use Clinically\Halaxy\Exceptions\HalaxyException;
use Clinically\Halaxy\Exceptions\MethodNotAllowedException;
use Clinically\Halaxy\Exceptions\NotFoundException;
use Clinically\Halaxy\Exceptions\RateLimitException;
use Clinically\Halaxy\Exceptions\ServerException;
use Clinically\Halaxy\Exceptions\ValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;

final class Client implements ClientInterface
{
    private const CONTENT_TYPE = 'application/fhir+json';

    private const PATCH_CONTENT_TYPE = 'application/merge-patch+json';

    public function __construct(
        private readonly AuthenticatorInterface $authenticator,
        private readonly Region $region,
        private readonly string $userAgent = 'Clinically Halaxy SDK',
        private readonly int $timeout = 30,
        private readonly int $retryAttempts = 3,
        private readonly int $retryDelay = 100,
    ) {}

    /**
     * Make a GET request to the API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $endpoint, array $query = []): Response
    {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * Make a POST request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->request('POST', $endpoint, [], $data);
    }

    /**
     * Make a PUT request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        return $this->request('PUT', $endpoint, [], $data);
    }

    /**
     * Make a PATCH request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->request('PATCH', $endpoint, [], $data);
    }

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint): Response
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Get the base URL for the configured region.
     */
    public function getBaseUrl(): string
    {
        return $this->region->baseUrl();
    }

    /**
     * Create a new client instance for a specific region.
     */
    public function forRegion(string $region): static
    {
        return new self(
            authenticator: $this->authenticator,
            region: Region::fromString($region),
            userAgent: $this->userAgent,
            timeout: $this->timeout,
            retryAttempts: $this->retryAttempts,
            retryDelay: $this->retryDelay,
        );
    }

    /**
     * Make a request to the API.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        array $data = [],
    ): Response {
        $url = $this->buildUrl($endpoint);

        $httpResponse = $this->makeRequest($method, $url, $query, $data);

        $this->handleErrors($httpResponse, $method, $url);

        return new Response($httpResponse);
    }

    /**
     * Build the full URL for an endpoint.
     */
    private function buildUrl(string $endpoint): string
    {
        return rtrim($this->getBaseUrl(), '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Create and configure the HTTP request.
     */
    private function createRequest(string $method): PendingRequest
    {
        $request = Http::withToken($this->authenticator->getAccessToken())
            ->withHeaders([
                'Accept' => self::CONTENT_TYPE,
                'User-Agent' => $this->userAgent,
            ])
            ->timeout($this->timeout)
            ->retry(
                $this->retryAttempts,
                $this->retryDelay,
                /** @phpstan-ignore-next-line */
                fn (\Throwable $e, PendingRequest $request): bool => $this->shouldRetry($e),
                throw: false,
            );

        // Use different content type for PATCH requests
        if ($method === 'PATCH') {
            $request = $request->contentType(self::PATCH_CONTENT_TYPE);
        } else {
            $request = $request->contentType(self::CONTENT_TYPE);
        }

        return $request;
    }

    /**
     * Make the HTTP request.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     */
    private function makeRequest(
        string $method,
        string $url,
        array $query = [],
        array $data = [],
    ): HttpResponse {
        $request = $this->createRequest($method);

        return match ($method) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Determine if a request should be retried.
     */
    private function shouldRetry(\Exception $e): bool
    {
        // Retry on connection errors or 5xx server errors
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status >= 500 && $status !== 501;
        }

        return false;
    }

    /**
     * Handle error responses from the API.
     */
    private function handleErrors(HttpResponse $response, string $method, string $url): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        match ($status) {
            401 => throw AuthenticationException::tokenExpired(),
            403 => throw ForbiddenException::insufficientPermissions(),
            404 => throw NotFoundException::fromResponse($response, $method, $url),
            405 => throw MethodNotAllowedException::fromResponse($response, $method, $url),
            422, 400 => throw $this->createValidationException($response, $method, $url),
            429 => throw $this->createRateLimitException($response),
            500 => throw ServerException::internalError(),
            503 => throw ServerException::serviceUnavailable(),
            default => throw HalaxyException::fromResponse($response, $method, $url),
        };
    }

    /**
     * Create a validation exception from a response.
     */
    private function createValidationException(HttpResponse $response, string $method, string $url): ValidationException
    {
        $body = $response->json();

        if (isset($body['resourceType']) && $body['resourceType'] === 'OperationOutcome') {
            return ValidationException::withErrors($body['issue'] ?? []);
        }

        // Extract message from response body
        $message = $body['message'] ?? $body['error'] ?? 'Validation failed';

        return new ValidationException(
            message: $message,
            statusCode: $response->status(),
            requestMethod: $method,
            requestUrl: $url,
            operationOutcome: $body,
        );
    }

    /**
     * Create a rate limit exception from a response.
     */
    private function createRateLimitException(HttpResponse $response): RateLimitException
    {
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            return RateLimitException::withRetryAfter((int) $retryAfter);
        }

        return new RateLimitException;
    }
}
