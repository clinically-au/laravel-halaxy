<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Clinical;

use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Resource;

final class DocumentReferenceResource extends Resource
{
    use HasCreate;

    protected string $resourceType = 'DocumentReference';

    protected string $endpoint = 'DocumentReference';
}
