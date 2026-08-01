<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Financial;

use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Concerns\HasUpdate;
use Clinically\Halaxy\Resources\Resource;

final class CoverageResource extends Resource
{
    use HasCreate;
    use HasFind;
    use HasList;
    use HasUpdate;

    protected string $resourceType = 'Coverage';

    protected string $endpoint = 'Coverage';
}
