<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Thrown when Halaxy rejects the HTTP method for a resource.
 *
 * Halaxy supports a different set of interactions per resource — several
 * resources are create-and-search only, with no update of any kind. The
 * authoritative list is the CapabilityStatement (`Halaxy::capabilities()`),
 * and the SDK's resource classes mirror it by omitting the traits for
 * operations Halaxy does not implement.
 *
 * Reaching a 405 therefore almost always means calling the underlying client
 * directly (`getClient()->patch(...)`) for an interaction the curated resource
 * surface deliberately does not expose.
 */
final class MethodNotAllowedException extends HalaxyException
{
    /**
     * Create an exception from an HTTP response.
     *
     * Halaxy answers a rejected method with an empty body rather than an
     * OperationOutcome, so the message is built from the request itself —
     * otherwise this surfaces as a bare "An unknown error occurred".
     */
    public static function fromResponse(
        Response $response,
        ?string $method = null,
        ?string $url = null,
    ): self {
        $body = $response->json();
        $allowed = $response->header('Allow');

        $message = self::extractMessage($body)
            ?? trim(sprintf(
                'Halaxy does not support %s on %s.%s',
                $method ?? 'this method',
                $url ?? 'this endpoint',
                $allowed === '' ? '' : " Allowed methods: {$allowed}.",
            ));

        return new self(
            message: $message,
            statusCode: $response->status(),
            requestMethod: $method,
            requestUrl: $url,
            operationOutcome: $body,
        );
    }
}
