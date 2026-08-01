<?php

declare(strict_types=1);

namespace Clinically\Halaxy\DTOs;

use DateTimeInterface;

final readonly class ReferralPayload
{
    /**
     * @param  list<ReferralAttachment>  $attachments
     * @param  array{start?: string, end?: string}|null  $period
     */
    public function __construct(
        public string $coverageReference,
        public string $subjectReference,
        public string $requesterReference,
        public DateTimeInterface $created,
        public bool $active = true,
        public ?string $comment = null,
        public array $attachments = [],
        public ?array $period = null,
        public ?string $performerReference = null,
        public ?string $referralDefinitionReference = null,
        public ?bool $includeCancelledAppointment = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'resourceType' => 'Referral',
            'coverage' => $this->reference($this->coverageReference, 'Coverage'),
            'period' => $this->period,
            'performer' => $this->optionalReference($this->performerReference, 'Practitioner'),
            'requester' => $this->reference($this->requesterReference, 'PractitionerRole'),
            'referralDefinition' => $this->optionalReference($this->referralDefinitionReference, 'ReferralDefinition'),
            'created' => $this->created->format(DateTimeInterface::ATOM),
            'active' => $this->active,
            'subject' => $this->reference($this->subjectReference, 'Patient'),
            'includeCancelledAppointment' => $this->includeCancelledAppointment,
            'comment' => $this->comment,
            'attachments' => $this->attachments === []
                ? null
                : array_map(
                    static fn (ReferralAttachment $attachment): array => $attachment->toArray(),
                    $this->attachments,
                ),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{reference: string, type: string}
     */
    private function reference(string $reference, string $type): array
    {
        return [
            'reference' => $reference,
            'type' => $type,
        ];
    }

    /**
     * @return array{reference: string, type: string}|null
     */
    private function optionalReference(?string $reference, string $type): ?array
    {
        return $reference === null ? null : $this->reference($reference, $type);
    }
}
