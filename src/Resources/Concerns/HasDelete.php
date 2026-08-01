<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasDelete
{
    /**
     * Delete a resource.
     */
    public function delete(string $id): Response
    {
        return $this->client->delete("{$this->endpoint}/{$id}");
    }
}
