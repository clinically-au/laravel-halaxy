<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\People;

use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Concerns\HasReplace;
use Clinically\Halaxy\Resources\Concerns\HasUpdate;
use Clinically\Halaxy\Resources\Resource;

final class PatientResource extends Resource
{
    use HasCreate;
    use HasFind;
    use HasList;
    use HasReplace;
    use HasUpdate;

    protected string $resourceType = 'Patient';

    protected string $endpoint = 'Patient';

    /**
     * Export a list of patient references/IDs.
     */
    public function exportIds(): Response
    {
        return $this->client->get('Patient/$export-ids');
    }
}
