<?php

declare(strict_types=1);

/*
 * Write endpoints deliberately NOT exercised against the live practice.
 * Each creates records that the API cannot delete or reliably deduplicate,
 * so running them repeatedly would permanently pollute the production
 * practice (as happened with duplicate test patients before ID pinning).
 * Their request shapes are covered by the Feature suite against fakes.
 * Remove a skip only with the practice owner's explicit agreement.
 */

it('creates practitioners (professional contacts)', function () {})
    ->skip('Not run live: no delete API and no reliable dedup — would accumulate professional contacts in the production practice.');

it('creates practitioner roles', function () {})
    ->skip('Not run live: no delete API and no reliable dedup — would accumulate professional contact roles in the production practice.');

it('creates organizations (professional contact practices)', function () {})
    ->skip('Not run live: no delete API and no reliable dedup — would accumulate contact practices in the production practice.');

it('creates schedules', function () {})
    ->skip('Not run live: creates real practitioner availability in the production calendar with no delete API.');

it('generates schedule slots', function () {})
    ->skip('Not run live: materialises real bookable slots in the production calendar.');

it('creates coverage for a patient', function () {})
    ->skip('Not run live: requires a real funder Organization reference and has no delete API.');

it('creates referrals', function () {})
    ->skip('Not run live: requires practice-specific referral definitions and has no delete API.');
