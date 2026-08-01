<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Scheduling;

use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class SlotResource extends Resource
{
    use HasFind;
    use HasList;

    protected string $resourceType = 'Slot';

    protected string $endpoint = 'Slot';
}
