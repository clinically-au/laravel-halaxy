<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Contracts;

use Clinically\Halaxy\Http\Response;

interface ClientInterface
{
    /**
     * Make a GET request to the API.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $endpoint, array $query = []): Response;

    /**
     * Make a POST request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response;

    /**
     * Make a PUT request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response;

    /**
     * Make a PATCH request to the API.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response;

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint): Response;

    /**
     * Get the base URL for the configured region.
     */
    public function getBaseUrl(): string;

    /**
     * Create a new client instance for a specific region.
     */
    public function forRegion(string $region): static;
}
