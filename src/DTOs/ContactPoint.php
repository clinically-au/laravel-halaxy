<?php

declare(strict_types=1);

namespace Clinically\Halaxy\DTOs;

use Clinically\Halaxy\Enums\ContactPointSystem;
use Clinically\Halaxy\Enums\ContactPointUse;
use InvalidArgumentException;

final readonly class ContactPoint
{
    private const string INTERNATIONAL_PHONE_PATTERN = '/^\+[1-9]\d{7,14}$/';

    private function __construct(
        public ContactPointSystem $system,
        public string $value,
        public ContactPointUse $use,
    ) {}

    public static function mobile(string $number): self
    {
        self::assertInternationalPhone($number);

        return new self(ContactPointSystem::Sms, $number, ContactPointUse::Mobile);
    }

    public static function phone(string $number, ContactPointUse $use = ContactPointUse::Home): self
    {
        self::assertInternationalPhone($number);
        self::assertFixedContactUse($use);

        return new self(ContactPointSystem::Phone, $number, $use);
    }

    public static function fax(string $number, ContactPointUse $use = ContactPointUse::Work): self
    {
        self::assertInternationalPhone($number);
        self::assertFixedContactUse($use);

        return new self(ContactPointSystem::Fax, $number, $use);
    }

    public static function email(string $email, ContactPointUse $use = ContactPointUse::Home): self
    {
        throw_unless(
            filter_var($email, FILTER_VALIDATE_EMAIL),
            InvalidArgumentException::class,
            'Email addresses sent to Halaxy must be valid.',
        );
        self::assertFixedContactUse($use);

        return new self(ContactPointSystem::Email, $email, $use);
    }

    /** @return array{system: string, value: string, use: string} */
    public function toArray(): array
    {
        return [
            'system' => $this->system->value,
            'value' => $this->value,
            'use' => $this->use->value,
        ];
    }

    private static function assertInternationalPhone(string $number): void
    {
        throw_unless(
            preg_match(self::INTERNATIONAL_PHONE_PATTERN, $number) === 1,
            InvalidArgumentException::class,
            'Phone numbers sent to Halaxy must use compact international format.',
        );
    }

    private static function assertFixedContactUse(ContactPointUse $use): void
    {
        throw_if(
            $use === ContactPointUse::Mobile,
            InvalidArgumentException::class,
            'Mobile contact points must use the Halaxy SMS contact type.',
        );
    }
}
