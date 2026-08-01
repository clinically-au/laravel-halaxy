<?php

declare(strict_types=1);

use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Tests\IntegrationTestCase;

/*
 * Write-gated, self-cleaning lifecycle for the Appointment endpoints:
 * find an available time, book it for the sentinel test patient, fetch it,
 * patch its comment, then cancel it via the modifierExtension recipe so
 * the practice calendar is left as we found it (invoice zeroed).
 */

it('books, updates, and cancels an appointment for the sentinel patient', function () {
    $this->skipUnlessWritesAllowed();

    $patientId = IntegrationTestCase::curatedPatientIds()[0];
    $baseUrl = Region::fromString($_ENV['HALAXY_REGION'] ?? 'au')->baseUrl();

    // Booking needs a healthcare service (appointment type).
    $services = Halaxy::healthcareServices()->list(['_count' => 1]);
    $serviceId = $services->entries()[0]['resource']['id'] ?? null;

    if ($serviceId === null) {
        $this->markTestSkipped('Practice has no HealthcareService to book against');
    }

    // Find a genuinely available time reasonably far out.
    $available = Halaxy::appointments()->findAvailable([
        'start' => date('c', strtotime('+30 days 09:00')),
        'end' => date('c', strtotime('+44 days 17:00')),
        'duration' => '30',
        'show' => 'first-available',
    ]);

    expect($available->successful())->toBeTrue();

    $slot = $available->entries()[0]['resource'] ?? null;

    if ($slot === null || ! isset($slot['start'], $slot['end'])) {
        $this->markTestSkipped('No available appointment times in the next 30-44 days');
    }

    $practitionerRoleRef = null;

    foreach ($slot['participant'] ?? [] as $participant) {
        if (str_contains($participant['actor']['reference'] ?? '', 'PractitionerRole/')) {
            $practitionerRoleRef = $participant['actor']['reference'];
        }
    }

    if ($practitionerRoleRef === null) {
        $this->markTestSkipped('Available time did not include a PractitionerRole participant');
    }

    // Book it for the sentinel patient.
    $booked = Halaxy::appointments()->book([
        'parameter' => [
            [
                'name' => 'appt-resource',
                'resource' => [
                    'resourceType' => 'Appointment',
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'minutesDuration' => 30,
                    'participant' => [
                        ['actor' => ['type' => 'PractitionerRole', 'reference' => $practitionerRoleRef]],
                    ],
                ],
            ],
            [
                'name' => 'patient-id',
                'valueReference' => ['type' => 'Patient', 'reference' => "{$baseUrl}Patient/{$patientId}"],
            ],
            [
                'name' => 'healthcare-service-id',
                'valueReference' => ['type' => 'HealthcareService', 'reference' => "{$baseUrl}HealthcareService/{$serviceId}"],
            ],
            ['name' => 'location-type', 'valueCode' => 'clinic'],
            ['name' => 'status', 'valueCode' => 'booked'],
        ],
    ]);

    expect($booked->successful())->toBeTrue()
        ->and($booked->resourceType())->toBe('Appointment');

    $appointmentId = $booked->json()['id'] ?? null;

    expect($appointmentId)->not->toBeNull();

    // Fetch it back and confirm it belongs to the sentinel patient.
    $found = Halaxy::appointments()->find($appointmentId);

    $patientRefs = array_filter(
        array_column(array_column($found->json()['participant'] ?? [], 'actor'), 'reference'),
        fn (string $ref): bool => str_contains($ref, "Patient/{$patientId}"),
    );

    expect($found->successful())->toBeTrue()
        ->and($patientRefs)->not->toBeEmpty();

    // Exercise plain PATCH on the appointment.
    $updated = Halaxy::appointments()->update($appointmentId, [
        'comment' => 'SDK integration test booking — cancelled automatically',
    ]);

    expect($updated->successful())->toBeTrue();

    // Cancel (no charge) via the modifierExtension recipe — self-cleaning.
    $cancelled = Halaxy::appointments()->update($appointmentId, [
        'participant' => [
            [
                'actor' => ['type' => 'Patient', 'reference' => "{$baseUrl}Patient/{$patientId}"],
                'modifierExtension' => [
                    [
                        'url' => 'https://terminology.halaxy.com/StructureDefinition/appointment-participant-status',
                        'valueCoding' => [
                            'system' => str_replace('/main/', '/presets/', $baseUrl).'CodeSystem/appointment-participant-status',
                            'code' => 'cancelled (no charge)',
                            'display' => 'cancelled (no charge)',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($cancelled->successful())->toBeTrue();

    $afterCancel = Halaxy::appointments()->find($appointmentId);

    expect($afterCancel->successful())->toBeTrue();

    // Halaxy appointments carry no top-level status; cancellation shows as
    // the patient participant's modifierExtension coding.
    $participantStatus = null;

    foreach ($afterCancel->json()['participant'] ?? [] as $participant) {
        if (! str_contains($participant['actor']['reference'] ?? '', "Patient/{$patientId}")) {
            continue;
        }

        $participantStatus = $participant['modifierExtension'][0]['valueCoding']['code'] ?? null;
    }

    expect($participantStatus)->toBe('cancelled');
});
