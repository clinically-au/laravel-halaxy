<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\People;

use Clinically\Halaxy\DTOs\PatientPayload;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Concerns\HasReplace;
use Clinically\Halaxy\Resources\Concerns\HasUpdate;
use Clinically\Halaxy\Resources\Resource;

final class PatientResource extends Resource
{
    use HasCreate {
        create as private createFromArray;
    }
    use HasFind;
    use HasList;
    use HasReplace {
        replace as private replaceFromArray;
    }
    use HasUpdate {
        update as private updateFromArray;
    }

    protected string $resourceType = 'Patient';

    protected string $endpoint = 'Patient';

    /** @param array<string, mixed>|PatientPayload $data */
    public function create(array|PatientPayload $data): Response
    {
        return $this->createFromArray($this->payloadData($data));
    }

    /** @param array<string, mixed>|PatientPayload $data */
    public function update(string $id, array|PatientPayload $data): Response
    {
        return $this->updateFromArray($id, $this->payloadData($data));
    }

    /** @param array<string, mixed>|PatientPayload $data */
    public function replace(string $id, array|PatientPayload $data): Response
    {
        return $this->replaceFromArray($id, $this->payloadData($data));
    }

    /**
     * Export a list of patient references/IDs.
     */
    public function exportIds(): Response
    {
        return $this->client->get('Patient/$export-ids');
    }

    /**
     * @param  array<string, mixed>|PatientPayload  $data
     * @return array<string, mixed>
     */
    private function payloadData(array|PatientPayload $data): array
    {
        return $data instanceof PatientPayload ? $data->toArray() : $data;
    }
}
