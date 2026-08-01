<?php

declare(strict_types=1);

use Clinically\Halaxy\Facades\Halaxy;

/*
 * Read-only coverage of the practice-administration endpoints (no patient
 * data): list each resource, and where the practice has records, fetch one
 * by ID. Nothing here writes, and nothing touches patient records.
 */

dataset('administrative resources', [
    'practitioners' => ['practitioners', 'Practitioner'],
    'practitioner roles' => ['practitionerRoles', 'PractitionerRole'],
    'organizations' => ['organizations', 'Organization'],
    'healthcare services' => ['healthcareServices', 'HealthcareService'],
    'charge item definitions' => ['chargeItemDefinitions', 'ChargeItemDefinition'],
    'schedules' => ['schedules', 'Schedule'],
    'slots' => ['slots', 'Slot'],
    'referral definitions' => ['referralDefinitions', 'ReferralDefinition'],
]);

it('lists and gets {dataset}', function (string $method, string $resourceType) {
    $list = Halaxy::$method()->list(['_count' => 2]);

    expect($list->successful())->toBeTrue()
        ->and($list->isBundle())->toBeTrue();

    $firstId = $list->entries()[0]['resource']['id'] ?? null;

    if ($firstId === null) {
        return; // Practice has no records of this type; list shape verified.
    }

    $found = Halaxy::$method()->find($firstId);

    expect($found->successful())->toBeTrue()
        ->and($found->resourceType())->toBe($resourceType);
})->with('administrative resources');

it('lists search parameters', function () {
    $response = Halaxy::searchParameters()->list();

    expect($response->successful())->toBeTrue();
});

it('finds available appointment times', function () {
    $response = Halaxy::appointments()->findAvailable([
        'start' => date('c', strtotime('+7 days 09:00')),
        'end' => date('c', strtotime('+14 days 17:00')),
        'duration' => '30',
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});

it('exports patient ids', function () {
    // Returns references for every patient in the practice. Assert shape
    // only — the contents are never inspected, logged, or asserted on.
    $response = Halaxy::patients()->exportIds();

    expect($response->successful())->toBeTrue()
        ->and($response->json()['resourceType'] ?? null)->toBe('Parameters')
        ->and($response->json()['parameter'] ?? null)->toBeArray();
});
