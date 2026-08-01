<?php

declare(strict_types=1);

use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Tests\IntegrationTestCase;

/*
 * Read-only smoke tests against the live Halaxy API. These authenticate with
 * the credentials in .env and only ever GET the curated test patients listed
 * in HALAXY_TEST_PATIENT_IDS — never create, update, or delete anything.
 */

it('authenticates and fetches the capability statement', function () {
    $response = Halaxy::capabilities()->get();

    expect($response->successful())->toBeTrue()
        ->and($response->resourceType())->toBe('CapabilityStatement');
});

it('reads each curated test patient', function () {
    $patientIds = IntegrationTestCase::curatedPatientIds();

    if ($patientIds === []) {
        $this->markTestSkipped('No HALAXY_TEST_PATIENT_IDS configured in .env');
    }

    foreach ($patientIds as $patientId) {
        $response = Halaxy::patients()->find($patientId);

        expect($response->successful())->toBeTrue()
            ->and($response->resourceType())->toBe('Patient')
            ->and($response->json()['id'] ?? null)->toBe($patientId);
    }
});
