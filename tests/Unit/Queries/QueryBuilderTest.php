<?php

declare(strict_types=1);

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Pagination\FhirPaginator;
use Clinically\Halaxy\Queries\QueryBuilder;
use Illuminate\Http\Client\Response as HttpResponse;

function createQueryBuilder(?ClientInterface $client = null): QueryBuilder
{
    $client ??= Mockery::mock(ClientInterface::class);

    return new QueryBuilder($client, 'Patient', 'Patient');
}

function createResponse(array $body = [], int $status = 200): Response
{
    $httpResponse = new HttpResponse(
        new GuzzleHttp\Psr7\Response($status, [], json_encode($body)),
    );

    return new Response($httpResponse);
}

test('build returns empty array by default', function (): void {
    $builder = createQueryBuilder();

    expect($builder->build())->toBe([]);
});

test('where with two arguments sets parameter directly', function (): void {
    $builder = createQueryBuilder();

    $builder->where('name', 'John');

    expect($builder->build())->toBe(['name' => 'John']);
});

test('where with eq operator sets value without prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', 'eq', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => '2000-01-01']);
});

test('where with equals sign sets value without prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', '=', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => '2000-01-01']);
});

test('where with ne operator adds ne prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('status', 'ne', 'active');

    expect($builder->build())->toBe(['status' => 'neactive']);
});

test('where with != operator adds ne prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('status', '!=', 'active');

    expect($builder->build())->toBe(['status' => 'neactive']);
});

test('where with gt operator adds gt prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', 'gt', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'gt2000-01-01']);
});

test('where with > operator adds gt prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', '>', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'gt2000-01-01']);
});

test('where with lt operator adds lt prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', 'lt', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'lt2000-01-01']);
});

test('where with < operator adds lt prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', '<', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'lt2000-01-01']);
});

test('where with ge operator adds ge prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', 'ge', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'ge2000-01-01']);
});

test('where with >= operator adds ge prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', '>=', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'ge2000-01-01']);
});

test('where with le operator adds le prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', 'le', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'le2000-01-01']);
});

test('where with <= operator adds le prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('birthdate', '<=', '2000-01-01');

    expect($builder->build())->toBe(['birthdate' => 'le2000-01-01']);
});

test('where with sa operator adds sa prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('date', 'sa', '2024-01-01');

    expect($builder->build())->toBe(['date' => 'sa2024-01-01']);
});

test('where with eb operator adds eb prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('date', 'eb', '2024-01-01');

    expect($builder->build())->toBe(['date' => 'eb2024-01-01']);
});

test('where with ap operator adds ap prefix', function (): void {
    $builder = createQueryBuilder();

    $builder->where('date', 'ap', '2024-01-01');

    expect($builder->build())->toBe(['date' => 'ap2024-01-01']);
});

test('where with contains modifier uses parameter modifier syntax', function (): void {
    $builder = createQueryBuilder();

    $builder->where('name', 'contains', 'John');

    expect($builder->build())->toBe(['name:contains' => 'John']);
});

test('where with exact modifier uses parameter modifier syntax', function (): void {
    $builder = createQueryBuilder();

    $builder->where('name', 'exact', 'John Smith');

    expect($builder->build())->toBe(['name:exact' => 'John Smith']);
});

test('where with not modifier uses parameter modifier syntax', function (): void {
    $builder = createQueryBuilder();

    $builder->where('status', 'not', 'inactive');

    expect($builder->build())->toBe(['status:not' => 'inactive']);
});

test('where with array value sets array directly', function (): void {
    $builder = createQueryBuilder();

    $builder->where('identifier', '=', ['123', '456']);

    expect($builder->build())->toBe(['identifier' => ['123', '456']]);
});

test('where is chainable', function (): void {
    $builder = createQueryBuilder();

    $result = $builder
        ->where('name', 'John')
        ->where('birthdate', 'gt', '2000-01-01')
        ->where('status', 'active');

    expect($result)->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->build())->toBe([
            'name' => 'John',
            'birthdate' => 'gt2000-01-01',
            'status' => 'active',
        ]);
});

test('whereId with string sets _id parameter', function (): void {
    $builder = createQueryBuilder();

    $builder->whereId('123');

    expect($builder->build())->toBe(['_id' => '123']);
});

test('whereId with array joins ids with comma', function (): void {
    $builder = createQueryBuilder();

    $builder->whereId(['123', '456', '789']);

    expect($builder->build())->toBe(['_id' => '123,456,789']);
});

test('whereLastUpdated delegates to where', function (): void {
    $builder = createQueryBuilder();

    $builder->whereLastUpdated('gt', '2024-01-01');

    expect($builder->build())->toBe(['_lastUpdated' => 'gt2024-01-01']);
});

test('include adds resource type prefixed include', function (): void {
    $builder = createQueryBuilder();

    $builder->include('generalPractitioner');

    expect($builder->build())->toBe([
        '_include' => ['Patient:generalPractitioner'],
    ]);
});

test('include can be called multiple times', function (): void {
    $builder = createQueryBuilder();

    $builder->include('generalPractitioner')->include('organization');

    expect($builder->build())->toBe([
        '_include' => [
            'Patient:generalPractitioner',
            'Patient:organization',
        ],
    ]);
});

test('includeIterate sets _include:iterate parameter', function (): void {
    $builder = createQueryBuilder();

    $builder->includeIterate('PractitionerRole', 'practitioner');

    expect($builder->build())->toBe([
        '_include:iterate' => 'PractitionerRole:practitioner',
    ]);
});

test('orderBy adds ascending sort', function (): void {
    $builder = createQueryBuilder();

    $builder->orderBy('name');

    expect($builder->build())->toBe(['_sort' => 'name']);
});

test('orderBy with asc direction adds ascending sort', function (): void {
    $builder = createQueryBuilder();

    $builder->orderBy('name', 'asc');

    expect($builder->build())->toBe(['_sort' => 'name']);
});

test('orderBy with desc direction adds descending sort', function (): void {
    $builder = createQueryBuilder();

    $builder->orderBy('name', 'desc');

    expect($builder->build())->toBe(['_sort' => '-name']);
});

test('orderBy is case insensitive for direction', function (): void {
    $builder = createQueryBuilder();

    $builder->orderBy('name', 'DESC');

    expect($builder->build())->toBe(['_sort' => '-name']);
});

test('orderByDesc adds descending sort', function (): void {
    $builder = createQueryBuilder();

    $builder->orderByDesc('birthdate');

    expect($builder->build())->toBe(['_sort' => '-birthdate']);
});

test('multiple sorts are joined with comma', function (): void {
    $builder = createQueryBuilder();

    $builder->orderBy('family')->orderByDesc('birthdate')->orderBy('given');

    expect($builder->build())->toBe(['_sort' => 'family,-birthdate,given']);
});

test('summary with true sets _summary to true string', function (): void {
    $builder = createQueryBuilder();

    $builder->summary(true);

    expect($builder->build())->toBe(['_summary' => 'true']);
});

test('summary with false sets _summary to false string', function (): void {
    $builder = createQueryBuilder();

    $builder->summary(false);

    expect($builder->build())->toBe(['_summary' => 'false']);
});

test('summary with string sets _summary directly', function (): void {
    $builder = createQueryBuilder();

    $builder->summary('text');

    expect($builder->build())->toBe(['_summary' => 'text']);
});

test('summary without arguments defaults to true', function (): void {
    $builder = createQueryBuilder();

    $builder->summary();

    expect($builder->build())->toBe(['_summary' => 'true']);
});

test('count sets _summary to count', function (): void {
    $builder = createQueryBuilder();

    $builder->count();

    expect($builder->build())->toBe(['_summary' => 'count']);
});

test('total sets _total parameter', function (): void {
    $builder = createQueryBuilder();

    $builder->total('accurate');

    expect($builder->build())->toBe(['_total' => 'accurate']);
});

test('total without arguments defaults to accurate', function (): void {
    $builder = createQueryBuilder();

    $builder->total();

    expect($builder->build())->toBe(['_total' => 'accurate']);
});

test('withoutTotal sets _total to none', function (): void {
    $builder = createQueryBuilder();

    $builder->withoutTotal();

    expect($builder->build())->toBe(['_total' => 'none']);
});

test('limit sets _count parameter', function (): void {
    $builder = createQueryBuilder();

    $builder->limit(50);

    expect($builder->build())->toBe(['_count' => 50]);
});

test('page sets page parameter', function (): void {
    $builder = createQueryBuilder();

    $builder->page(3);

    expect($builder->build())->toBe(['page' => 3]);
});

test('build combines all parameters', function (): void {
    $builder = createQueryBuilder();

    $builder
        ->where('name', 'contains', 'John')
        ->where('birthdate', 'gt', '2000-01-01')
        ->whereId('123')
        ->include('generalPractitioner')
        ->orderBy('family')
        ->orderByDesc('birthdate')
        ->limit(25)
        ->page(2)
        ->total('accurate');

    $result = $builder->build();

    expect($result)->toBe([
        'name:contains' => 'John',
        'birthdate' => 'gt2000-01-01',
        '_id' => '123',
        '_count' => 25,
        'page' => 2,
        '_total' => 'accurate',
        '_include' => ['Patient:generalPractitioner'],
        '_sort' => 'family,-birthdate',
    ]);
});

test('clone creates independent copy', function (): void {
    $builder = createQueryBuilder();
    $builder->where('name', 'John')->limit(10);

    $clone = $builder->clone();
    $clone->where('status', 'active')->limit(20);

    expect($builder->build())->toBe([
        'name' => 'John',
        '_count' => 10,
    ])
        ->and($clone->build())->toBe([
            'name' => 'John',
            '_count' => 20,
            'status' => 'active',
        ]);
});

test('clone copies includes', function (): void {
    $builder = createQueryBuilder();
    $builder->include('generalPractitioner');

    $clone = $builder->clone();
    $clone->include('organization');

    expect($builder->build()['_include'])->toBe(['Patient:generalPractitioner'])
        ->and($clone->build()['_include'])->toBe([
            'Patient:generalPractitioner',
            'Patient:organization',
        ]);
});

test('clone copies sorts', function (): void {
    $builder = createQueryBuilder();
    $builder->orderBy('name');

    $clone = $builder->clone();
    $clone->orderByDesc('birthdate');

    expect($builder->build()['_sort'])->toBe('name')
        ->and($clone->build()['_sort'])->toBe('name,-birthdate');
});

test('get executes query with built parameters', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $response = createResponse(['resourceType' => 'Patient', 'id' => '123']);

    $client->shouldReceive('get')
        ->once()
        ->with('Patient', ['name' => 'John', '_count' => 10])
        ->andReturn($response);

    $builder = new QueryBuilder($client, 'Patient', 'Patient');
    $result = $builder->where('name', 'John')->limit(10)->get();

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->json()['id'])->toBe('123');
});

test('first sets limit to 1 and executes query', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $response = createResponse(['resourceType' => 'Patient', 'id' => '456']);

    $client->shouldReceive('get')
        ->once()
        ->with('Patient', ['_count' => 1])
        ->andReturn($response);

    $builder = new QueryBuilder($client, 'Patient', 'Patient');
    $result = $builder->first();

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->json()['id'])->toBe('456');
});

test('paginate returns FhirPaginator', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $response = createResponse([
        'resourceType' => 'Bundle',
        'type' => 'searchset',
        'total' => 100,
        'entry' => [
            ['resource' => ['resourceType' => 'Patient', 'id' => '1']],
            ['resource' => ['resourceType' => 'Patient', 'id' => '2']],
        ],
    ]);

    $client->shouldReceive('get')
        ->once()
        ->with('Patient', ['_count' => 25])
        ->andReturn($response);

    $builder = new QueryBuilder($client, 'Patient', 'Patient');
    $paginator = $builder->paginate(25);

    expect($paginator)->toBeInstanceOf(FhirPaginator::class)
        ->and($paginator->total())->toBe(100)
        ->and($paginator->count())->toBe(2);
});

test('all methods are fluent', function (): void {
    $builder = createQueryBuilder();

    expect($builder->where('a', 'b'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->whereId('1'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->whereLastUpdated('gt', '2024-01-01'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->include('ref'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->includeIterate('Type', 'ref'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->orderBy('field'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->orderByDesc('field'))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->summary())->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->count())->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->total())->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->withoutTotal())->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->limit(10))->toBeInstanceOf(QueryBuilder::class)
        ->and($builder->page(1))->toBeInstanceOf(QueryBuilder::class);
});
