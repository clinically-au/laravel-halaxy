<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Patient\PatientCreated;
use Clinically\Halaxy\Events\Patient\PatientUpdated;
use Spatie\WebhookClient\Models\WebhookCall;

test('PatientCreated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;
    $webhookCall->name = 'halaxy';

    $event = new PatientCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: '2024-01-15T10:30:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Patient/123456789')
        ->and($event->timestamp)->toBe('2024-01-15T10:30:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('PatientCreated patientId extracts id from full URL reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->patientId())->toBe('123456789');
});

test('PatientCreated patientId extracts id from short reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientCreated(
        resourceReference: 'Patient/987654321',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->patientId())->toBe('987654321');
});

test('PatientCreated patientId returns null for empty reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientCreated(
        resourceReference: '',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->patientId())->toBeNull();
});

test('PatientUpdated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientUpdated(
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: '2024-01-15T11:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Patient/123456789')
        ->and($event->timestamp)->toBe('2024-01-15T11:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('PatientUpdated patientId extracts id from reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientUpdated(
        resourceReference: 'Patient/12345',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->patientId())->toBe('12345');
});

test('PatientCreated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientCreated(
        resourceReference: 'Patient/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('PatientUpdated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new PatientUpdated(
        resourceReference: 'Patient/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});
