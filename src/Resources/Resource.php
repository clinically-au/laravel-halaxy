<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Queries\QueryBuilder;

abstract class Resource
{
    /**
     * The FHIR resource type name (e.g., 'Patient', 'Appointment').
     */
    protected string $resourceType;

    /**
     * The API endpoint for this resource.
     */
    protected string $endpoint;

    public function __construct(
        protected readonly ClientInterface $client,
    ) {}

    /**
     * Get the resource type name.
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /**
     * Get the API endpoint.
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Create a new query builder for this resource.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->client, $this->endpoint, $this->resourceType);
    }
}
