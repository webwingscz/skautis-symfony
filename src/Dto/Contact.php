<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Dto;

/**
 * A phone number or e-mail of a person (OrganizationUnit.PersonContactAll).
 */
final readonly class Contact
{
    public function __construct(
        public int $id,
        public string $type,
        public string $typeLabel,
        public string $value,
        public bool $isParent,
    ) {
    }

    public static function fromApi(object $row, bool $isParent = false): self
    {
        return new self(
            id: (int) ($row->ID ?? 0),
            type: (string) ($row->ID_ContactType ?? ''),
            typeLabel: (string) ($row->ContactType ?? ''),
            value: trim((string) ($row->Value ?? '')),
            isParent: $isParent,
        );
    }

    public function isPhone(): bool
    {
        return str_contains(strtolower($this->type), 'telefon');
    }

    public function isEmail(): bool
    {
        return str_contains(strtolower($this->type), 'email') || str_contains($this->value, '@');
    }

    /**
     * Digits only, so it can go straight into a tel: link.
     */
    public function dialable(): string
    {
        return preg_replace('~[^\d+]~', '', $this->value) ?? $this->value;
    }
}
