<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasList
{
    /**
     * List resources with optional query parameters.
     *
     * @param  array<string, mixed>  $query
     */
    public function list(array $query = []): Response
    {
        return $this->client->get($this->endpoint, $query);
    }

    /**
     * List all resources (alias for list).
     *
     * @param  array<string, mixed>  $query
     */
    public function all(array $query = []): Response
    {
        return $this->list($query);
    }
}
