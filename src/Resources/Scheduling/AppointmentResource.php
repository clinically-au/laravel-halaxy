<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Scheduling;

use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Concerns\HasUpdate;
use Clinically\Halaxy\Resources\Resource;

final class AppointmentResource extends Resource
{
    use HasFind;
    use HasList;
    use HasUpdate;

    protected string $resourceType = 'Appointment';

    protected string $endpoint = 'Appointment';

    /**
     * Book a new appointment via the $book operation.
     *
     * Expects a FHIR Parameters resource with parameters such as
     * appt-resource, patient-id, healthcare-service-id, location-type,
     * and status. Halaxy creates related resources (invoice, clinical
     * note) as part of the booking.
     *
     * @param  array<string, mixed>  $data
     */
    public function book(array $data): Response
    {
        if (! isset($data['resourceType'])) {
            $data['resourceType'] = 'Parameters';
        }

        return $this->client->post('Appointment/$book', $data);
    }

    /**
     * Find available appointment times via the $find operation.
     *
     * Required params: start, end, duration. Optional: practitioner-role,
     * practitioner, organization, show, emergency, apply-buffer-time, _sort.
     *
     * @param  array<string, mixed>  $params
     */
    public function findAvailable(array $params = []): Response
    {
        return $this->client->get('Appointment/$find', $params);
    }

    /**
     * Create an appointment (alias for book).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Response
    {
        return $this->book($data);
    }
}
