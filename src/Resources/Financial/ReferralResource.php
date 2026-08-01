<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Resources\Financial;

use Clinically\Halaxy\DTOs\ReferralAttachment;
use Clinically\Halaxy\DTOs\ReferralPayload;
use Clinically\Halaxy\Http\Response;
use Clinically\Halaxy\Resources\Concerns\HasCreate;
use Clinically\Halaxy\Resources\Concerns\HasFind;
use Clinically\Halaxy\Resources\Concerns\HasList;
use Clinically\Halaxy\Resources\Resource;
use InvalidArgumentException;

final class ReferralResource extends Resource
{
    use HasCreate {
        create as private createFromArray;
    }
    use HasFind;
    use HasList;

    protected string $resourceType = 'Referral';

    protected string $endpoint = 'Referral';

    /**
     * Create a referral using Halaxy's referral schema.
     *
     * @param  array<string, mixed>|ReferralPayload  $data
     */
    public function create(array|ReferralPayload $data): Response
    {
        return $this->createFromArray(
            $data instanceof ReferralPayload ? $data->toArray() : $data,
        );
    }

    /**
     * Add attachments to an existing referral.
     *
     * Halaxy's PATCH endpoint only supports appending attachments. Existing
     * attachments and other referral properties are not changed.
     */
    public function addAttachments(string $id, ReferralAttachment ...$attachments): Response
    {
        if ($attachments === []) {
            throw new InvalidArgumentException('At least one referral attachment is required.');
        }

        return $this->update($id, [
            'resourceType' => $this->resourceType,
            'attachments' => array_map(
                static fn (ReferralAttachment $attachment): array => $attachment->toArray(),
                $attachments,
            ),
        ]);
    }

    /**
     * Append attachments using Halaxy's attachment-only PATCH endpoint.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Response
    {
        $unsupportedFields = array_diff(
            array_keys($data),
            ['resourceType', 'attachments'],
        );

        if ($unsupportedFields !== []) {
            throw new InvalidArgumentException(sprintf(
                'Halaxy referrals only support attachment updates; unsupported fields: %s.',
                implode(', ', $unsupportedFields),
            ));
        }

        return $this->client->patch("{$this->endpoint}/{$id}", $data);
    }
}
