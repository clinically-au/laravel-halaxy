<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasReplace
{
    /**
     * Replace a resource (full update using PUT).
     *
     * Destructive: writable properties omitted from $data are removed
     * from the resource.
     *
     * @param  array<string, mixed>  $data
     */
    public function replace(string $id, array $data): Response
    {
        // Ensure resourceType and id are set
        if (! isset($data['resourceType'])) {
            $data['resourceType'] = $this->resourceType;
        }
        if (! isset($data['id'])) {
            $data['id'] = $id;
        }

        return $this->client->put("{$this->endpoint}/{$id}", $data);
    }
}
