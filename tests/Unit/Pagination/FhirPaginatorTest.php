<?php

declare(strict_types=1);

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Pagination\FhirPaginator;
use Illuminate\Http\Client\Response as HttpResponse;

function createPaginatorResponse(array $body = [], int $status = 200): Response
{
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response($status, [], json_encode($body)),
    );

    return new Response($httpResponse);
}

function createPaginator(
    array $responseBody = [],
    int $perPage = 50,
    ?ClientInterface $client = null,
): FhirPaginator {
    $client ??= Mockery::mock(ClientInterface::class);
    $response = createPaginatorResponse($responseBody);

    return new FhirPaginator(
        client: $client,
        response: $response,
        perPage: $perPage,
    );
}

test('implements Countable', function (): void {
    $paginator = createPaginator();

    expect($paginator)->toBeInstanceOf(Countable::class);
});

test('implements Iterator', function (): void {
    $paginator = createPaginator();

    expect($paginator)->toBeInstanceOf(Iterator::class);
});

test('items returns extracted resources from bundle entries', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 2,
        'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'id' => '1']],
            ['resource' => ['resourceType' => 'Patient', 'id' => '2']],
        ],
    ]);

    $items = $paginator->items();

    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe('1')
        ->and($items[1]['id'])->toBe('2');
});

test('items returns empty array when no entries', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 0,
    ]);

    expect($paginator->items())->toBe([]);
});

test('items ignores entries without resource key', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'id' => '1']],
            ['fullUrl' => 'http://example.com/Patient/2'], // No resource
            ['resource' => ['resourceType' => 'Patient', 'id' => '3']],
        ],
    ]);

    $items = $paginator->items();

    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe('1')
        ->and($items[1]['id'])->toBe('3');
});

test('total returns total from bundle', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 150,
        'entry' => [],
    ]);

    expect($paginator->total())->toBe(150);
});

test('total returns null when not in bundle', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [],
    ]);

    expect($paginator->total())->toBeNull();
});

test('perPage returns configured value', function (): void {
    $paginator = createPaginator(perPage: 25);

    expect($paginator->perPage())->toBe(25);
});

test('count returns number of items on current page', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 100,
        'entry' => [
            ['resource' => ['id' => '1']],
            ['resource' => ['id' => '2']],
            ['resource' => ['id' => '3']],
        ],
    ]);

    expect(count($paginator))->toBe(3);
});

test('hasNextPage returns true when next link exists', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://api.example.com/Patient'],
            ['relation' => 'next', 'url' => 'http://api.example.com/Patient?page=2'],
        ],
        'entry' => [],
    ]);

    expect($paginator->hasNextPage())->toBeTrue();
});

test('hasNextPage returns false when no next link', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'self', 'url' => 'http://api.example.com/Patient'],
        ],
        'entry' => [],
    ]);

    expect($paginator->hasNextPage())->toBeFalse();
});

test('nextPageUrl returns next link URL', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'link' => [
            ['relation' => 'next', 'url' => 'http://api.example.com/Patient?page=2'],
        ],
        'entry' => [],
    ]);

    expect($paginator->nextPageUrl())->toBe('http://api.example.com/Patient?page=2');
});

test('nextPageUrl returns null when no next link', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [],
    ]);

    expect($paginator->nextPageUrl())->toBeNull();
});

test('nextPage returns null when no next page', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [],
    ]);

    expect($paginator->nextPage())->toBeNull();
});

test('nextPage fetches and returns new paginator', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('getBaseUrl')
        ->andReturn('https://au-api.halaxy.com/main/');
    $client->shouldReceive('get')
        ->with('Patient?page=2')
        ->andReturn(createPaginatorResponse([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 100,
            'entry' => [
                ['resource' => ['id' => '51']],
                ['resource' => ['id' => '52']],
            ],
        ]));

    $paginator = new FhirPaginator(
        client: $client,
        response: createPaginatorResponse([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 100,
            'link' => [
                ['relation' => 'next', 'url' => 'https://au-api.halaxy.com/main/Patient?page=2'],
            ],
            'entry' => [
                ['resource' => ['id' => '1']],
            ],
        ]),
        perPage: 50,
    );

    $nextPage = $paginator->nextPage();

    expect($nextPage)->toBeInstanceOf(FhirPaginator::class)
        ->and($nextPage->items())->toHaveCount(2)
        ->and($nextPage->items()[0]['id'])->toBe('51');
});

test('iterator works correctly', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            ['resource' => ['id' => 'a']],
            ['resource' => ['id' => 'b']],
            ['resource' => ['id' => 'c']],
        ],
    ]);

    $ids = [];
    foreach ($paginator as $key => $item) {
        $ids[$key] = $item['id'];
    }

    expect($ids)->toBe([0 => 'a', 1 => 'b', 2 => 'c']);
});

test('iterator can be rewound', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'entry' => [
            ['resource' => ['id' => 'a']],
            ['resource' => ['id' => 'b']],
        ],
    ]);

    // First iteration
    $first = [];
    foreach ($paginator as $item) {
        $first[] = $item['id'];
    }

    // Rewind and iterate again
    $second = [];
    foreach ($paginator as $item) {
        $second[] = $item['id'];
    }

    expect($first)->toBe(['a', 'b'])
        ->and($second)->toBe(['a', 'b']);
});

test('all iterates through all pages', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('getBaseUrl')
        ->andReturn('https://au-api.halaxy.com/main/');
    $client->shouldReceive('get')
        ->with('Patient?page=2')
        ->once()
        ->andReturn(createPaginatorResponse([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'entry' => [
                ['resource' => ['id' => '3']],
                ['resource' => ['id' => '4']],
            ],
        ]));

    $paginator = new FhirPaginator(
        client: $client,
        response: createPaginatorResponse([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'link' => [
                ['relation' => 'next', 'url' => 'https://au-api.halaxy.com/main/Patient?page=2'],
            ],
            'entry' => [
                ['resource' => ['id' => '1']],
                ['resource' => ['id' => '2']],
            ],
        ]),
        perPage: 2,
    );

    $allIds = [];
    foreach ($paginator->all() as $item) {
        $allIds[] = $item['id'];
    }

    expect($allIds)->toBe(['1', '2', '3', '4']);
});

test('toArray returns pagination data', function (): void {
    $paginator = createPaginator([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 100,
        'link' => [
            ['relation' => 'next', 'url' => 'http://example.com/Patient?page=2'],
        ],
        'entry' => [
            ['resource' => ['id' => '1']],
            ['resource' => ['id' => '2']],
        ],
    ], perPage: 50);

    $array = $paginator->toArray();

    expect($array)->toBe([
        'data' => [
            ['id' => '1'],
            ['id' => '2'],
        ],
        'total' => 100,
        'per_page' => 50,
        'has_more' => true,
        'next_page_url' => 'http://example.com/Patient?page=2',
    ]);
});

test('handles empty response gracefully', function (): void {
    $paginator = createPaginator([]);

    expect($paginator->items())->toBe([])
        ->and($paginator->total())->toBeNull()
        ->and($paginator->hasNextPage())->toBeFalse()
        ->and(count($paginator))->toBe(0);
});

test('handles null json response gracefully', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response(200, [], 'not json'),
    );
    $response = new Response($httpResponse);

    $paginator = new FhirPaginator(
        client: $client,
        response: $response,
        perPage: 50,
    );

    expect($paginator->items())->toBe([])
        ->and($paginator->total())->toBeNull();
});
