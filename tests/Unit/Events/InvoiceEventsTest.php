<?php

declare(strict_types=1);

use Clinically\Halaxy\Events\Invoice\InvoiceCreated;
use Clinically\Halaxy\Events\Invoice\InvoiceDeleted;
use Clinically\Halaxy\Events\Invoice\InvoiceUpdated;
use Spatie\WebhookClient\Models\WebhookCall;

test('InvoiceCreated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;
    $webhookCall->name = 'halaxy';

    $event = new InvoiceCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Invoice/INV-001',
        timestamp: '2024-01-25T09:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Invoice/INV-001')
        ->and($event->timestamp)->toBe('2024-01-25T09:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('InvoiceCreated invoiceId extracts id from full URL reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceCreated(
        resourceReference: 'https://au-api.halaxy.com/main/Invoice/INV-001',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->invoiceId())->toBe('INV-001');
});

test('InvoiceCreated invoiceId extracts id from short reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceCreated(
        resourceReference: 'Invoice/INV-002',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->invoiceId())->toBe('INV-002');
});

test('InvoiceCreated invoiceId returns null for empty reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceCreated(
        resourceReference: '',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->invoiceId())->toBeNull();
});

test('InvoiceUpdated can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceUpdated(
        resourceReference: 'https://au-api.halaxy.com/main/Invoice/INV-001',
        timestamp: '2024-01-25T10:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Invoice/INV-001')
        ->and($event->timestamp)->toBe('2024-01-25T10:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('InvoiceUpdated invoiceId extracts id from reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceUpdated(
        resourceReference: 'Invoice/INV-003',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->invoiceId())->toBe('INV-003');
});

test('InvoiceDeleted can be created with all properties', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceDeleted(
        resourceReference: 'https://au-api.halaxy.com/main/Invoice/INV-001',
        timestamp: '2024-01-25T11:00:00Z',
        webhookCall: $webhookCall,
    );

    expect($event->resourceReference)->toBe('https://au-api.halaxy.com/main/Invoice/INV-001')
        ->and($event->timestamp)->toBe('2024-01-25T11:00:00Z')
        ->and($event->webhookCall)->toBe($webhookCall);
});

test('InvoiceDeleted invoiceId extracts id from reference', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceDeleted(
        resourceReference: 'Invoice/INV-004',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->invoiceId())->toBe('INV-004');
});

test('InvoiceCreated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceCreated(
        resourceReference: 'Invoice/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('InvoiceUpdated timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceUpdated(
        resourceReference: 'Invoice/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});

test('InvoiceDeleted timestamp can be null', function (): void {
    $webhookCall = new WebhookCall;

    $event = new InvoiceDeleted(
        resourceReference: 'Invoice/123',
        timestamp: null,
        webhookCall: $webhookCall,
    );

    expect($event->timestamp)->toBeNull();
});
