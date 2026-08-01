<?php

declare(strict_types=1);

use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Tests\IntegrationTestCase;

/*
 * Lifecycle test for the SDK-managed test cohort ("Test, Patient" and
 * "Test, Another"): resolve each sentinel patient, create it if missing,
 * find it, and update it.
 *
 * Resolution order: the pinned IDs in HALAXY_TEST_PATIENT_IDS (first ID =
 * Patient Test, second = Another Test), falling back to an exact-name
 * search. Pin the IDs — Halaxy drops the `identifier` field on create, so
 * identifier tagging cannot mark our records, and an unpinned run that
 * fails to find a sentinel will create a new one.
 *
 * Halaxy's Patient API supports search/create/patch/PUT-replace but NOT
 * delete, and `active` is documented read-only (archiving is UI-only), so
 * the sentinels persist and are reused by every run. The final step asserts
 * that limitation so we notice if Halaxy ever lifts it.
 *
 * PUT replace is destructive: writable properties omitted from the payload
 * are removed from the profile (only attachments are retained). Writable
 * fields: name, telecom, gender, birthDate, deceasedBoolean, address,
 * contact. `identifier`, `active`, `id`, `generalPractitioner`, and
 * `managingOrganization` are read-only; `attachments` is write-only.
 */

const SDK_TEST_COHORT = [
    ['family' => 'Test', 'given' => 'Patient'],
    ['family' => 'Test', 'given' => 'Another'],
];

/**
 * Locate a sentinel by exact name match; anything else in the search
 * response is discarded unexamined.
 *
 * @param  array{family: string, given: string}  $sentinel
 * @return array<string, mixed>|null
 */
function locateSentinelByName(array $sentinel): ?array
{
    $response = Halaxy::patients()->query()
        ->where('family', $sentinel['family'])
        ->where('given', $sentinel['given'])
        ->get();

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();

    foreach ($response->entries() as $entry) {
        $resource = $entry['resource'] ?? [];
        $name = $resource['name'][0] ?? [];

        if (($name['family'] ?? null) === $sentinel['family']
            && ($name['given'][0] ?? null) === $sentinel['given']) {
            return $resource;
        }
    }

    return null;
}

it('provisions and exercises the test cohort', function () {
    $this->skipUnlessWritesAllowed();

    $pinnedIds = IntegrationTestCase::curatedPatientIds();

    foreach (SDK_TEST_COHORT as $index => $sentinel) {
        // 1. Resolve the sentinel: pinned ID first, then exact-name search.
        $patientId = $pinnedIds[$index] ?? null;

        if ($patientId === null) {
            $existing = locateSentinelByName($sentinel);
            $patientId = $existing['id'] ?? null;
        }

        // 2. Create it only if it genuinely does not exist.
        if ($patientId === null) {
            $created = Halaxy::patients()->create([
                'name' => [[
                    'use' => 'official',
                    'family' => $sentinel['family'],
                    'given' => [$sentinel['given']],
                ]],
            ]);

            expect($created->successful())->toBeTrue()
                ->and($created->resourceType())->toBe('Patient');

            $patientId = $created->json()['id'] ?? null;
        }

        expect($patientId)->not->toBeNull();

        // 3. Find it by ID and verify it is our sentinel.
        $found = Halaxy::patients()->find($patientId);

        expect($found->successful())->toBeTrue()
            ->and($found->resourceType())->toBe('Patient')
            ->and($found->json()['name'][0]['family'] ?? null)->toBe($sentinel['family'])
            ->and($found->json()['name'][0]['given'][0] ?? null)->toBe($sentinel['given']);

        // 4. Update it (merge-patch) and verify the change round-trips.
        $updated = Halaxy::patients()->update($patientId, [
            'telecom' => [[
                'system' => 'phone',
                'value' => '+61 2 0000 0000',
                'use' => 'work',
            ]],
        ]);

        expect($updated->successful())->toBeTrue();

        $afterUpdate = Halaxy::patients()->find($patientId);
        $phones = array_column($afterUpdate->json()['telecom'] ?? [], 'value');

        expect($phones)->toContain('+61 2 0000 0000');

        // 5. Replace it (PUT). Destructive semantics: omitted writable
        //    properties are removed, so this both applies the new phone
        //    and removes the merge-patched one from step 4.
        $replaced = Halaxy::patients()->replace($patientId, [
            'name' => [[
                'use' => 'official',
                'family' => $sentinel['family'],
                'given' => [$sentinel['given']],
            ]],
            'telecom' => [[
                'system' => 'phone',
                'value' => '+61 2 1111 1111',
                'use' => 'work',
            ]],
            'birthDate' => '2000-01-01',
        ]);

        expect($replaced->successful())->toBeTrue();

        $afterReplace = Halaxy::patients()->find($patientId);
        $phonesAfterReplace = array_column($afterReplace->json()['telecom'] ?? [], 'value');

        expect($phonesAfterReplace)->toContain('+61 2 1111 1111')
            ->not->toContain('+61 2 0000 0000')
            ->and($afterReplace->json()['birthDate'] ?? null)->toBe('2000-01-01')
            ->and($afterReplace->json()['name'][0]['family'] ?? null)->toBe($sentinel['family']);

        // 6. Delete/deactivate is not possible: no delete interaction, and
        //    `active` is read-only — writes to it are silently ignored.
        //    Assert that limitation still holds so an API change surfaces.
        $deactivated = Halaxy::patients()->update($patientId, ['active' => false]);

        expect($deactivated->successful())->toBeTrue();

        $afterDeactivate = Halaxy::patients()->find($patientId);

        expect($afterDeactivate->json()['active'] ?? null)->toBeTrue();
    }
});
