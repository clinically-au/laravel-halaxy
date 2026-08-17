<?php

declare(strict_types=1);

use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Exceptions\NotFoundException;
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

/*
 * The author contract, pinned against the live API.
 *
 * Halaxy keeps two disjoint PractitionerRole namespaces: the practice's own
 * roles (`PR-…`, profile hx-practitioner-role, organization `CL-…`) and
 * external referrer roles created through the API (`EP-…`, profile
 * hx-external-practitioner-role, organization `SP-…`). Both are returned by
 * PractitionerRole search, so a caller cannot tell them apart by whether the
 * reference resolves — `PractitionerRole/EP-…` answers 200 to a GET.
 *
 * Only a practice role may author a DocumentReference. Naming an external
 * role produces 404 "Author not found", which reads like a broken reference
 * and is not: the role exists, it is simply not an eligible author.
 */

it('rejects an external practitioner role as a document author', function () {
    $this->skipUnlessWritesAllowed();

    $patientId = IntegrationTestCase::curatedPatientIds()[0];
    $baseUrl = Region::fromString($_ENV['HALAXY_REGION'] ?? 'au')->baseUrl();

    $externalRoleId = collect(Halaxy::practitionerRoles()->list(['_count' => 100])->entries())
        ->pluck('resource.id')
        ->first(fn (string $id): bool => str_starts_with($id, 'EP-'));

    if ($externalRoleId === null) {
        $this->markTestSkipped('Practice has no external PractitionerRole to test with');
    }

    // The role itself resolves — this is not a dangling reference.
    expect(Halaxy::practitionerRoles()->find($externalRoleId)->successful())->toBeTrue();

    Halaxy::documentReferences()->create([
        'docStatus' => 'preliminary',
        'description' => 'SDK Integration Test Note (external author)',
        'subject' => ['type' => 'Patient', 'reference' => "{$baseUrl}Patient/{$patientId}"],
        'author' => [['type' => 'PractitionerRole', 'reference' => "{$baseUrl}PractitionerRole/{$externalRoleId}"]],
        'content' => [['attachment' => [
            'contentType' => 'text/html',
            'data' => '<p>Created by the clinically/laravel-halaxy SDK integration suite. Safe to delete.</p>',
        ]]],
    ]);
})->throws(NotFoundException::class, 'Author not found');

it('creates a clinical note with no author at all', function () {
    $this->skipUnlessWritesAllowed();

    $patientId = IntegrationTestCase::curatedPatientIds()[0];
    $baseUrl = Region::fromString($_ENV['HALAXY_REGION'] ?? 'au')->baseUrl();

    // Consumers holding only an external referrer must be able to file the
    // document unattributed rather than misattribute it to a practice
    // clinician who did not write it.
    $response = Halaxy::documentReferences()->create([
        'docStatus' => 'preliminary',
        'description' => 'SDK Integration Test Note (no author)',
        'subject' => ['type' => 'Patient', 'reference' => "{$baseUrl}Patient/{$patientId}"],
        'content' => [['attachment' => [
            'contentType' => 'text/html',
            'data' => '<p>Created by the clinically/laravel-halaxy SDK integration suite. Safe to delete.</p>',
        ]]],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->resourceType())->toBe('DocumentReference');
});
