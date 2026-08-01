<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Financial;

use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class ReferralDefinitionResource extends Resource
{
    use HasFind;
    use HasList;

    protected string $resourceType = 'ReferralDefinition';

    protected string $endpoint = 'ReferralDefinition';
}
