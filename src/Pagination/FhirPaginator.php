<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Pagination;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Http\Response;
use Countable;
use Iterator;

/**
 * @implements Iterator<int, array<string, mixed>>
 */
final class FhirPaginator implements Countable, Iterator
{
    private int $currentIndex = 0;

    private ?int $total = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $items = [];

    private ?string $nextPageUrl = null;

    public function __construct(
        private readonly ClientInterface $client,
        Response $response,
        private readonly int $perPage,
    ) {
        $this->processResponse($response);
    }

    /**
     * Process a response and extract items and pagination info.
     */
    private function processResponse(Response $response): void
    {
        $json = $response->json();

        if ($json === null) {
            return;
        }

        $this->total = $response->total();
        $this->nextPageUrl = $response->nextPageUrl();

        // Extract items from bundle entries
        foreach ($response->entries() as $entry) {
            if (isset($entry['resource'])) {
                $this->items[] = $entry['resource'];
            }
        }
    }

    /**
     * Get all items on the current page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Get the total number of items (if available).
     */
    public function total(): ?int
    {
        return $this->total;
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get the number of items on the current page.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Determine if there is a next page.
     */
    public function hasNextPage(): bool
    {
        return $this->nextPageUrl !== null;
    }

    /**
     * Get the next page URL.
     */
    public function nextPageUrl(): ?string
    {
        return $this->nextPageUrl;
    }

    /**
     * Fetch the next page and return a new paginator.
     */
    public function nextPage(): ?self
    {
        if (! $this->hasNextPage()) {
            return null;
        }

        // Extract path from full URL
        $url = $this->nextPageUrl;
        $baseUrl = $this->client->getBaseUrl();
        $path = str_replace($baseUrl, '', $url);

        $response = $this->client->get($path);

        return new self(
            client: $this->client,
            response: $response,
            perPage: $this->perPage,
        );
    }

    /**
     * Iterate through all pages and yield items.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function all(): \Generator
    {
        $paginator = $this;

        while ($paginator !== null) {
            foreach ($paginator->items() as $item) {
                yield $item;
            }

            $paginator = $paginator->nextPage();
        }
    }

    // Iterator implementation

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return $this->items[$this->currentIndex];
    }

    public function key(): int
    {
        return $this->currentIndex;
    }

    public function next(): void
    {
        $this->currentIndex++;
    }

    public function rewind(): void
    {
        $this->currentIndex = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->currentIndex]);
    }

    /**
     * Convert to array representation suitable for Laravel pagination.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'has_more' => $this->hasNextPage(),
            'next_page_url' => $this->nextPageUrl,
        ];
    }
}
