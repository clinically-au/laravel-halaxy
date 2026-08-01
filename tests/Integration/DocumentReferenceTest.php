<?php

declare(strict_types=1);

use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Tests\IntegrationTestCase;

/*
 * Write-gated coverage of DocumentReference create (the only documented
 * DocumentReference operation): attach a draft clinical note to the
 * sentinel test patient. There is no delete/list for DocumentReference,
 * so each run adds one clearly-marked draft note to the sentinel profile.
 */

it('creates a clinical note on the sentinel patient', function () {
    $this->skipUnlessWritesAllowed();

    $patientId = IntegrationTestCase::curatedPatientIds()[0];
    $baseUrl = Region::fromString($_ENV['HALAXY_REGION'] ?? 'au')->baseUrl();

    $roles = Halaxy::practitionerRoles()->list(['_count' => 1]);
    $roleId = $roles->entries()[0]['resource']['id'] ?? null;

    if ($roleId === null) {
        $this->markTestSkipped('Practice has no PractitionerRole to author the note');
    }

    $response = Halaxy::documentReferences()->create([
        'docStatus' => 'preliminary',
        'description' => 'SDK Integration Test Note',
        'subject' => [
            'type' => 'Patient',
            'reference' => "{$baseUrl}Patient/{$patientId}",
        ],
        'author' => [
            [
                'type' => 'PractitionerRole',
                'reference' => "{$baseUrl}PractitionerRole/{$roleId}",
            ],
        ],
        'content' => [
            [
                'attachment' => [
                    'contentType' => 'text/html',
                    'data' => '<p>Created by the clinically/laravel-halaxy SDK integration suite. Safe to delete.</p>',
                ],
            ],
        ],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->resourceType())->toBe('DocumentReference');
});
