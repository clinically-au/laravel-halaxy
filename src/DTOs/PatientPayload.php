<?php

declare(strict_types=1);

namespace Clinically\Halaxy\DTOs;

final readonly class PatientPayload
{
    /**
     * @param  list<array<string, mixed>>  $identifier
     * @param  list<array<string, mixed>>  $name
     * @param  list<ContactPoint>  $telecom
     * @param  list<array<string, mixed>>  $address
     * @param  list<array<string, mixed>>  $contact
     * @param  list<array<string, mixed>>  $extension
     * @param  list<array<string, mixed>>  $attachments
     */
    public function __construct(
        public array $name = [],
        public array $telecom = [],
        public array $identifier = [],
        public ?string $gender = null,
        public ?string $birthDate = null,
        public ?bool $deceasedBoolean = null,
        public array $address = [],
        public array $contact = [],
        public array $extension = [],
        public array $attachments = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'resourceType' => 'Patient',
            'identifier' => $this->identifier,
            'name' => $this->name,
            'telecom' => array_map(
                static fn (ContactPoint $contactPoint): array => $contactPoint->toArray(),
                $this->telecom,
            ),
            'gender' => $this->gender,
            'birthDate' => $this->birthDate,
            'deceasedBoolean' => $this->deceasedBoolean,
            'address' => $this->address,
            'contact' => $this->contact,
            'extension' => $this->extension,
            'attachments' => $this->attachments,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
