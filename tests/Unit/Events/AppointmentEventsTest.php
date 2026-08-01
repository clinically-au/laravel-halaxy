<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Appointment\AppointmentCreated;
use Clinically\Halaxy\Events\Appointment\AppointmentDeleted;
use Clinically\Halaxy\Events\Appointment\AppointmentUpdated;
use Spatie\WebhookClient\Models\WebhookCall;

test('AppointmentCreated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;
    $webhookCall->name = 'halaxy';

    $event = new AppointmentCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Appointment/55001',
        timestamp: '2024-01-20T14:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Appointment/55001')
        ->and($event->timestamp)->toBe('2024-01-20T14:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('AppointmentCreated appointmentId extracts id from full URL reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Appointment/55001',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->appointmentId())->toBe('55001');
});

test('AppointmentCreated appointmentId extracts id from short reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentCreated(
        resourceReference: 'Appointment/55002',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->appointmentId())->toBe('55002');
});

test('AppointmentCreated appointmentId returns null for empty reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentCreated(
        resourceReference: '',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->appointmentId())->toBeNull();
});

test('AppointmentUpdated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentUpdated(
        resourceReference: 'https://au-api.halaxy.com/main/Appointment/55001',
        timestamp: '2024-01-20T15:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Appointment/55001')
        ->and($event->timestamp)->toBe('2024-01-20T15:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('AppointmentUpdated appointmentId extracts id from reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentUpdated(
        resourceReference: 'Appointment/55003',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->appointmentId())->toBe('55003');
});

test('AppointmentDeleted can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentDeleted(
        resourceReference: 'https://au-api.halaxy.com/main/Appointment/55001',
        timestamp: '2024-01-20T16:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Appointment/55001')
        ->and($event->timestamp)->toBe('2024-01-20T16:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('AppointmentDeleted appointmentId extracts id from reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentDeleted(
        resourceReference: 'Appointment/55004',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->appointmentId())->toBe('55004');
});

test('AppointmentCreated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentCreated(
        resourceReference: 'Appointment/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('AppointmentUpdated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentUpdated(
        resourceReference: 'Appointment/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('AppointmentDeleted timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new AppointmentDeleted(
        resourceReference: 'Appointment/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});
