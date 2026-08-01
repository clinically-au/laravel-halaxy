<?php

declare(strict_types=1);

use Clinically\Halaxy\Http\Response;
use Illuminate\Http\Client\Response as HttpResponse;

function createTestResponse(array $body = [], int $status = 200, array $headers = []): Response
{
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response($status, $headers, json_encode($body)),
    );

    return new Response($httpResponse);
}

test('status returns HTTP status code', function (): void {
    $response = createTestResponse(status: 200);
    expect($response->status())->toBe(200);

    $response = createTestResponse(status: 404);
    expect($response->status())->toBe(404);

    $response = createTestResponse(status: 500);
    expect($response->status())->toBe(500);
});

test('successful returns true for 2xx status codes', function (): void {
    expect(createTestResponse(status: 200)->successful())->toBeTrue()
        ->and(createTestResponse(status: 201)->successful())->toBeTrue()
        ->and(createTestResponse(status: 204)->successful())->toBeTrue()
        ->and(createTestResponse(status: 299)->successful())->toBeTrue();
});

test('successful returns false for non-2xx status codes', function (): void {
    expect(createTestResponse(status: 400)->successful())->toBeFalse()
        ->and(createTestResponse(status: 401)->successful())->toBeFalse()
        ->and(createTestResponse(status: 404)->successful())->toBeFalse()
        ->and(createTestResponse(status: 500)->successful())->toBeFalse();
});

test('failed returns false for 2xx status codes', function (): void {
    expect(createTestResponse(status: 200)->failed())->toBeFalse()
        ->and(createTestResponse(status: 201)->failed())->toBeFalse();
});

test('failed returns true for non-2xx status codes', function (): void {
    expect(createTestResponse(status: 400)->failed())->toBeTrue()
        ->and(createTestResponse(status: 500)->failed())->toBeTrue();
});

test('json returns decoded body', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
        'id' => '123',
        'name' => [['family' => 'Smith']],
    ]);

    $json = $response->json();

    expect($json)->toBeArray()
        ->and($json['resourceType'])->toBe('Patient')
        ->and($json['id'])->toBe('123')
        ->and($json['name'][0]['family'])->toBe('Smith');
});

test('json returns null for non-array response', function (): void {
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response(200, [], '"just a string"'),
    );
    $response = new Response($httpResponse);

    expect($response->json())->toBeNull();
});

test('json returns null for invalid JSON', function (): void {
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response(200, [], 'not json at all'),
    );
    $response = new Response($httpResponse);

    expect($response->json())->toBeNull();
});

test('body returns raw response body', function (): void {
    $response = createTestResponse(['foo' => 'bar']);

    expect($response->body())->toBe('{"foo":"bar"}');
});

test('header returns specific header value', function (): void {
    $response = createTestResponse(
        headers: ['X-Custom-Header' => 'custom-value'],
    );

    expect($response->header('X-Custom-Header'))->toBe('custom-value');
});

test('header returns empty string for missing header', function (): void {
    $response = createTestResponse();

    expect($response->header('X-Missing'))->toBe('');
});

test('headers returns all headers', function (): void {
    $response = createTestResponse(
        headers: [
            'Content-Type' => 'application/json',
            'X-Custom' => 'value',
        ],
    );

    $headers = $response->headers();

    expect($headers)->toBeArray()
        ->and($headers)->toHaveKey('Content-Type')
        ->and($headers)->toHaveKey('X-Custom');
});

test('toHttpResponse returns underlying response', function (): void {
    $response = createTestResponse();

    expect($response->toHttpResponse())->toBeInstanceOf(HttpResponse::class);
});

test('isFhirResource returns true when resourceType is present', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
        'id' => '123',
    ]);

    expect($response->isFhirResource())->toBeTrue();
});

test('isFhirResource returns false when resourceType is missing', function (): void {
    $response = createTestResponse([
        'id' => '123',
        'name' => 'test',
    ]);

    expect($response->isFhirResource())->toBeFalse();
});

test('isFhirResource returns false for empty response', function (): void {
    $response = createTestResponse([]);

    expect($response->isFhirResource())->toBeFalse();
});

test('isBundle returns true for Bundle resourceType', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
    ]);

    expect($response->isBundle())->toBeTrue();
});

test('isBundle returns false for non-Bundle resourceType', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
    ]);

    expect($response->isBundle())->toBeFalse();
});

test('isBundle returns false for missing resourceType', function (): void {
    $response = createTestResponse([]);

    expect($response->isBundle())->toBeFalse();
});

test('resourceType returns resource type from response', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Practitioner',
    ]);

    expect($response->resourceType())->toBe('Practitioner');
});

test('resourceType returns null when not present', function (): void {
    $response = createTestResponse([]);

    expect($response->resourceType())->toBeNull();
});

test('total returns total from Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 150,
    ]);

    expect($response->total())->toBe(150);
});

test('total returns null when not a Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
        'id' => '123',
    ]);

    expect($response->total())->toBeNull();
});

test('total returns null when total is missing from Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
    ]);

    expect($response->total())->toBeNull();
});

test('entries returns entry array from Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            ['resource' => ['id' => '1']],
            ['resource' => ['id' => '2']],
        ],
    ]);

    $entries = $response->entries();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['resource']['id'])->toBe('1')
        ->and($entries[1]['resource']['id'])->toBe('2');
});

test('entries returns empty array when not a Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
    ]);

    expect($response->entries())->toBe([]);
});

test('entries returns empty array when entry is missing', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
    ]);

    expect($response->entries())->toBe([]);
});

test('links returns link map from Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://example.com/Patient'],
            ['relation' => 'next', 'url' => 'http://example.com/Patient?page=2'],
            ['relation' => 'previous', 'url' => 'http://example.com/Patient?page=0'],
        ],
    ]);

    $links = $response->links();

    expect($links)->toBe([
        'self' => 'http://example.com/Patient',
        'next' => 'http://example.com/Patient?page=2',
        'previous' => 'http://example.com/Patient?page=0',
    ]);
});

test('links returns empty array when not a Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
    ]);

    expect($response->links())->toBe([]);
});

test('links returns empty array when link is missing', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
    ]);

    expect($response->links())->toBe([]);
});

test('links ignores malformed link entries', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://example.com/Patient'],
            ['relation' => 'next'], // Missing url
            ['url' => 'http://example.com/Patient?page=3'], // Missing relation
        ],
    ]);

    $links = $response->links();

    expect($links)->toBe([
        'self' => 'http://example.com/Patient',
    ]);
});

test('nextPageUrl returns next link URL', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'next', 'url' => 'http://example.com/Patient?page=2'],
        ],
    ]);

    expect($response->nextPageUrl())->toBe('http://example.com/Patient?page=2');
});

test('nextPageUrl returns null when no next link', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://example.com/Patient'],
        ],
    ]);

    expect($response->nextPageUrl())->toBeNull();
});

test('hasNextPage returns true when next link exists', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'next', 'url' => 'http://example.com/Patient?page=2'],
        ],
    ]);

    expect($response->hasNextPage())->toBeTrue();
});

test('hasNextPage returns false when no next link', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://example.com/Patient'],
        ],
    ]);

    expect($response->hasNextPage())->toBeFalse();
});

test('hasNextPage returns false for non-Bundle', function (): void {
    $response = createTestResponse([
        'resourceType' => 'Patient',
    ]);

    expect($response->hasNextPage())->toBeFalse();
});
