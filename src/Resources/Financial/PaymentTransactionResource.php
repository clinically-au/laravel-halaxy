<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Financial;

use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;

final class PaymentTransactionResource extends Resource
{
    use HasFind;
    use HasList;

    protected string $resourceType = 'PaymentTransaction';

    protected string $endpoint = 'PaymentTransaction';
}
