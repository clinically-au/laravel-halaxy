<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\HalaxyWebhookReceived;
use Spatie\WebhookClient\Models\WebhookCall;

test('can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;
    $webhookCall->name = 'halaxy';
    $webhookCall->payload = ['test' => 'data'];

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: '2024-01-15T10:30:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->eventType)->toBe('Patient.create')
        ->and($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Patient/123456789')
        ->and($event->timestamp)->toBe('2024-01-15T10:30:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('resourceId extracts id from full URL reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceId())->toBe('123456789');
});

test('resourceId extracts id from short reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'Patient/123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceId())->toBe('123456789');
});

test('resourceId returns null for empty reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: '',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceId())->toBeNull();
});

test('resourceType extracts type from full URL reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'https://au-api.halaxy.com/main/Patient/123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBe('Patient');
});

test('resourceType extracts type from short reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'Patient/123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBe('Patient');
});

test('resourceType returns null for single-part reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: '123456789',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBeNull();
});

test('can handle Appointment references', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Appointment.update',
        resourceReference: 'https://au-api.halaxy.com/main/Appointment/55001',
        timestamp: '2024-01-20T14:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBe('Appointment')
        ->and($event->resourceId())->toBe('55001');
});

test('can handle Invoice references', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Invoice.create',
        resourceReference: 'https://au-api.halaxy.com/main/Invoice/INV-001',
        timestamp: '2024-01-25T09:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBe('Invoice')
        ->and($event->resourceId())->toBe('INV-001');
});

test('timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.delete',
        resourceReference: 'Patient/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('handles EU API references', function (): void {
    $webhookCall = new WebhookCall;

    $event = new HalaxyWebhookReceived(
        eventType: 'Patient.create',
        resourceReference: 'https://eu-api.halaxy.com/main/Patient/EU-123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->resourceType())->toBe('Patient')
        ->and($event->resourceId())->toBe('EU-123');
});
