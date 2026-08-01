<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\People;

use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class PractitionerResource extends Resource
{
    use HasCreate;
    use HasFind;
    use HasList;

    protected string $resourceType = 'Practitioner';

    protected string $endpoint = 'Practitioner';
}
