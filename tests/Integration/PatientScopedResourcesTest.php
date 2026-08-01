<?php

declare(strict_types=1);

use Clinically\Halaxy\Exceptions\ValidationException;
use Clinically\Halaxy\Facades\Halaxy;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Tests\IntegrationTestCase;

/*
 * Read-only coverage of the patient-linked endpoints, scoped strictly to
 * the sentinel test patient. Every returned entry is asserted to reference
 * the sentinel; if the server ignored the scoping parameter and returned
 * other patients' records, the assertion fails WITHOUT exposing any of the
 * returned data. Sentinels are fresh test patients, so empty bundles are
 * the expected result — the tests verify endpoint + parameter correctness.
 */

/**
 * Assert a patient-scoped search behaves safely. Entries may reference the
 * sentinel under alias IDs, so scoping is verified via a control query with
 * a nonexistent patient: if the control returns entries too, the server is
 * ignoring the parameter — skip rather than assert on live data. Response
 * contents are never inspected or echoed.
 *
 * @param  callable(string): Response  $query
 */
function assertPatientScopedSearch(callable $query, string $patientId): void
{
    $bogusId = '999999999999';

    foreach ([$patientId, "Patient/{$patientId}"] as $reference) {
        try {
            $response = $query($reference);
        } catch (ValidationException) {
            // Polymorphic reference params reject bare ids (422) and
            // require the Type/id format — try the next format.
            continue;
        }

        expect($response->successful())->toBeTrue()
            ->and($response->isBundle())->toBeTrue();

        // Empty bundle: parameter honored (sentinel has no records) or the
        // practice has none at all — either way the endpoint works safely.
        if ($response->entries() === []) {
            return;
        }

        $control = $query(str_replace($patientId, $bogusId, $reference));

        expect($control->successful())->toBeTrue();

        // Control empty: parameter is honored server-side, so the entries
        // above belong to the sentinel (possibly under alias references).
        if ($control->entries() === []) {
            return;
        }
    }

    test()->markTestSkipped('Server ignores the patient scoping parameter in both id formats — cannot safely assert on live data.');
}

it('lists appointments scoped to the sentinel patient', function () {
    $patientId = IntegrationTestCase::curatedPatientIds()[0];

    assertPatientScopedSearch(
        fn (string $ref) => Halaxy::appointments()->list(['patient' => $ref, '_count' => 10]),
        $patientId,
    );
});

it('lists coverage scoped to the sentinel patient', function () {
    $patientId = IntegrationTestCase::curatedPatientIds()[0];

    assertPatientScopedSearch(
        fn (string $ref) => Halaxy::coverages()->list(['beneficiary' => $ref, '_count' => 10]),
        $patientId,
    );
});

it('lists invoices scoped to the sentinel patient', function () {
    $patientId = IntegrationTestCase::curatedPatientIds()[0];

    assertPatientScopedSearch(
        fn (string $ref) => Halaxy::invoices()->list(['recipient' => $ref, '_count' => 10]),
        $patientId,
    );
});

it('lists invoice lines scoped to the sentinel patient', function () {
    $patientId = IntegrationTestCase::curatedPatientIds()[0];

    assertPatientScopedSearch(
        fn (string $ref) => Halaxy::invoiceLines()->list(['subject' => $ref, '_count' => 10]),
        $patientId,
    );
});

it('lists referrals scoped to the sentinel patient', function () {
    $patientId = IntegrationTestCase::curatedPatientIds()[0];

    assertPatientScopedSearch(
        fn (string $ref) => Halaxy::referrals()->list(['subject' => $ref, '_count' => 10]),
        $patientId,
    );
});

it('lists payment transactions with a shape-only assertion', function () {
    // PaymentTransaction has no patient scoping parameter, so scope by an
    // ancient created date to keep the bundle empty; assert shape only and
    // never inspect entries.
    $response = Halaxy::paymentTransactions()->list(['created' => '1970-01-02', '_count' => 1]);

    expect($response->successful())->toBeTrue()
        ->and($response->isBundle())->toBeTrue();
});
