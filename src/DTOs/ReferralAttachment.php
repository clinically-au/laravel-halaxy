<?php

declare(strict_types=1);

namespace Clinically\Halaxy\DTOs;

final readonly class ReferralAttachment
{
    public function __construct(
        public string $contentType,
        public string $data,
        public string $title,
    ) {}

    /**
     * @return array{contentType: string, data: string, title: string}
     */
    public function toArray(): array
    {
        return [
            'contentType' => $this->contentType,
            'data' => $this->data,
            'title' => $this->title,
        ];
    }
}
