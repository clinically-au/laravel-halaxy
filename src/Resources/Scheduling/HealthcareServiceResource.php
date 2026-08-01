<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Scheduling;

use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class HealthcareServiceResource extends Resource
{
    use HasFind;
    use HasList;

    protected string $resourceType = 'HealthcareService';

    protected string $endpoint = 'HealthcareService';
}
