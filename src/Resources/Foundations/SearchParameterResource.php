<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Foundations;

use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class SearchParameterResource extends Resource
{
    use HasList;

    protected string $resourceType = 'SearchParameter';

    protected string $endpoint = 'SearchParameter';
}
