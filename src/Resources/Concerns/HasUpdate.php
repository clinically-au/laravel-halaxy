<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasUpdate
{
    /**
     * Update a resource (partial update using PATCH).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Response
    {
        return $this->client->patch("{$this->endpoint}/{$id}", $data);
    }
}
