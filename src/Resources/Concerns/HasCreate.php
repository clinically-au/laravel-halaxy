<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Concerns;

use Clinically\Halaxy\Http\Response;

trait HasCreate
{
    /**
     * Create a new resource.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Response
    {
        // Ensure resourceType is set
        if (! isset($data['resourceType'])) {
            $data['resourceType'] = $this->resourceType;
        }

        return $this->client->post($this->endpoint, $data);
    }
}
