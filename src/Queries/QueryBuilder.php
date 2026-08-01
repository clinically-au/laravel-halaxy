<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Queries;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Pagination\FhirPaginator;

final class QueryBuilder
{
    /**
     * @var array<string, string|int|bool|array<int, string>>
     */
    private array $parameters = [];

    /**
     * @var array<int, string>
     */
    private array $includes = [];

    /**
     * @var array<int, string>
     */
    private array $sorts = [];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $endpoint,
        private readonly string $resourceType,
    ) {}

    /**
     * Add a where clause to the query.
     *
     * @param  string|array<int, string>|null  $value
     */
    public function where(string $parameter, string $operatorOrValue, string|array|null $value = null): self
    {
        // If only two arguments, operator is actually the value
        if ($value === null) {
            $this->parameters[$parameter] = $operatorOrValue;

            return $this;
        }

        // Handle FHIR prefix operators
        $prefix = match ($operatorOrValue) {
            'eq', '=' => '',
            'ne', '!=' => 'ne',
            'gt', '>' => 'gt',
            'lt', '<' => 'lt',
            'ge', '>=' => 'ge',
            'le', '<=' => 'le',
            'sa' => 'sa', // starts after
            'eb' => 'eb', // ends before
            'ap' => 'ap', // approximately
            default => $operatorOrValue,
        };

        // Handle string modifiers
        if (in_array($operatorOrValue, ['contains', 'exact', 'not'], true)) {
            $this->parameters["{$parameter}:{$operatorOrValue}"] = $value;

            return $this;
        }

        // Handle prefix for numeric/date values
        if (is_string($value)) {
            $this->parameters[$parameter] = $prefix.$value;
        } else {
            $this->parameters[$parameter] = $value;
        }

        return $this;
    }

    /**
     * Filter by ID or IDs.
     *
     * @param  string|array<int, string>  $ids
     */
    public function whereId(string|array $ids): self
    {
        if (is_array($ids)) {
            $this->parameters['_id'] = implode(',', $ids);
        } else {
            $this->parameters['_id'] = $ids;
        }

        return $this;
    }

    /**
     * Filter by last updated date.
     */
    public function whereLastUpdated(string $operator, string $date): self
    {
        return $this->where('_lastUpdated', $operator, $date);
    }

    /**
     * Add an _include clause.
     */
    public function include(string $reference): self
    {
        $this->includes[] = "{$this->resourceType}:{$reference}";

        return $this;
    }

    /**
     * Add an _include:iterate clause.
     */
    public function includeIterate(string $sourceType, string $reference): self
    {
        $this->parameters['_include:iterate'] = "{$sourceType}:{$reference}";

        return $this;
    }

    /**
     * Add a sort clause.
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $prefix = strtolower($direction) === 'desc' ? '-' : '';
        $this->sorts[] = $prefix.$field;

        return $this;
    }

    /**
     * Sort in descending order.
     */
    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'desc');
    }

    /**
     * Set the summary mode.
     */
    public function summary(string|bool $mode = true): self
    {
        if (is_bool($mode)) {
            $this->parameters['_summary'] = $mode ? 'true' : 'false';
        } else {
            $this->parameters['_summary'] = $mode;
        }

        return $this;
    }

    /**
     * Only return count of matching resources.
     */
    public function count(): self
    {
        $this->parameters['_summary'] = 'count';

        return $this;
    }

    /**
     * Set the total mode.
     */
    public function total(string $mode = 'accurate'): self
    {
        $this->parameters['_total'] = $mode;

        return $this;
    }

    /**
     * Disable total count for better performance.
     */
    public function withoutTotal(): self
    {
        return $this->total('none');
    }

    /**
     * Set the number of results per page.
     */
    public function limit(int $limit): self
    {
        $this->parameters['_count'] = $limit;

        return $this;
    }

    /**
     * Set the page number.
     */
    public function page(int $page): self
    {
        $this->parameters['page'] = $page;

        return $this;
    }

    /**
     * Build the query parameters array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $params = $this->parameters;

        // Add includes
        if (! empty($this->includes)) {
            $params['_include'] = $this->includes;
        }

        // Add sorts
        if (! empty($this->sorts)) {
            $params['_sort'] = implode(',', $this->sorts);
        }

        return $params;
    }

    /**
     * Execute the query and return the raw response.
     */
    public function get(): Response
    {
        return $this->client->get($this->endpoint, $this->build());
    }

    /**
     * Execute the query and return a paginator.
     */
    public function paginate(int $perPage = 50): FhirPaginator
    {
        $this->limit($perPage);

        $response = $this->get();

        return new FhirPaginator(
            client: $this->client,
            response: $response,
            perPage: $perPage,
        );
    }

    /**
     * Execute the query and return the first result.
     */
    public function first(): Response
    {
        $this->limit(1);

        return $this->get();
    }

    /**
     * Clone the query builder.
     */
    public function clone(): self
    {
        $clone = new self($this->client, $this->endpoint, $this->resourceType);
        $clone->parameters = $this->parameters;
        $clone->includes = $this->includes;
        $clone->sorts = $this->sorts;

        return $clone;
    }
}
