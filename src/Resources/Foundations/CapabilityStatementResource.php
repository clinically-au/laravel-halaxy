<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Foundations;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Http\Response;

final class CapabilityStatementResource
{
    public function __construct(
        protected readonly ClientInterface $client,
    ) {}

    /**
     * Get the API capability statement (metadata).
     */
    public function get(): Response
    {
        return $this->client->get('metadata');
    }
}
