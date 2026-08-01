<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Http;

use Illuminate\Http\Client\Response as HttpResponse;

final class Response
{
    public function __construct(
        private readonly HttpResponse $response,
    ) {}

    /**
     * Get the response status code.
     */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * Determine if the request was successful.
     */
    public function successful(): bool
    {
        return $this->response->successful();
    }

    /**
     * Determine if the request failed.
     */
    public function failed(): bool
    {
        return $this->response->failed();
    }

    /**
     * Get the JSON decoded body of the response.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $json = $this->response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Get the raw body of the response.
     */
    public function body(): string
    {
        return $this->response->body();
    }

    /**
     * Get a header from the response.
     */
    public function header(string $name): string
    {
        return $this->response->header($name);
    }

    /**
     * Get all headers from the response.
     *
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->response->headers();
    }

    /**
     * Get the underlying HTTP response.
     */
    public function toHttpResponse(): HttpResponse
    {
        return $this->response;
    }

    /**
     * Determine if the response is a FHIR resource.
     */
    public function isFhirResource(): bool
    {
        $json = $this->json();

        return $json !== null && isset($json['resourceType']);
    }

    /**
     * Determine if the response is a FHIR Bundle.
     */
    public function isBundle(): bool
    {
        $json = $this->json();

        return $json !== null
            && isset($json['resourceType'])
            && $json['resourceType'] === 'Bundle';
    }

    /**
     * Get the resource type from a FHIR response.
     */
    public function resourceType(): ?string
    {
        $json = $this->json();

        return $json['resourceType'] ?? null;
    }

    /**
     * Get the total count from a FHIR Bundle response.
     */
    public function total(): ?int
    {
        $json = $this->json();
        if (! $this->isBundle()) {
            return null;
        }

        return isset($json['total']) ? (int) $json['total'] : null;
    }

    /**
     * Get the entries from a FHIR Bundle response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        $json = $this->json();
        if (! $this->isBundle()) {
            return [];
        }

        return $json['entry'] ?? [];
    }

    /**
     * Get link URLs from a FHIR Bundle response.
     *
     * @return array<string, string>
     */
    public function links(): array
    {
        $json = $this->json();
        if (! $this->isBundle()) {
            return [];
        }

        $links = [];
        foreach ($json['link'] ?? [] as $link) {
            if (isset($link['relation'], $link['url'])) {
                $links[$link['relation']] = $link['url'];
            }
        }

        return $links;
    }

    /**
     * Get the next page URL from a FHIR Bundle response.
     */
    public function nextPageUrl(): ?string
    {
        return $this->links()['next'] ?? null;
    }

    /**
     * Determine if there is a next page.
     */
    public function hasNextPage(): bool
    {
        return $this->nextPageUrl() !== null;
    }
}
