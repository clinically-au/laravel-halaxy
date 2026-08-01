<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasFind
{
    /**
     * Find a resource by ID.
     */
    public function find(string $id): Response
    {
        return $this->client->get("{$this->endpoint}/{$id}");
    }
}
