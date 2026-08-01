<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Scheduling;

use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class ScheduleResource extends Resource
{
    use HasCreate;
    use HasFind;
    use HasList;

    protected string $resourceType = 'Schedule';

    protected string $endpoint = 'Schedule';

    /**
     * Generate slots for a schedule via the $generate operation.
     *
     * Optional $data fields: `period` (start/end at least 24 hours apart)
     * and `updatePlanningHorizon` (bool, default false). Returns a Bundle
     * of generated Slot resources.
     *
     * @param  array<string, mixed>  $data
     */
    public function generateSlots(string $id, array $data = []): Response
    {
        return $this->client->post("Schedule/{$id}/\$generate", $data);
    }
}
