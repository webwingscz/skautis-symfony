<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Dto;

/**
 * An organisational unit: středisko, oddíl, družina… (OrganizationUnit.UnitDetail).
 */
final readonly class Unit
{
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $registrationNumber,
        public ?string $unitType,
        public ?int $parentId,
        public ?string $street,
        public ?string $city,
        public ?string $postcode,
        public ?string $email,
        public ?string $phone,
        public ?string $www,
    ) {
    }

    public static function fromApi(object $row): self
    {
        return new self(
            id: (int) ($row->ID ?? 0),
            displayName: (string) ($row->DisplayName ?? $row->SortName ?? '?'),
            registrationNumber: self::str($row, 'RegistrationNumber'),
            unitType: self::str($row, 'UnitType'),
            parentId: isset($row->ID_UnitParent) ? (int) $row->ID_UnitParent : null,
            street: self::str($row, 'Street'),
            city: self::str($row, 'City'),
            postcode: self::str($row, 'Postcode'),
            email: self::str($row, 'Email'),
            phone: self::str($row, 'Phone'),
            www: self::str($row, 'WWW'),
        );
    }

    public function address(): ?string
    {
        $parts = array_filter([$this->street, trim(($this->postcode ?? '').' '.($this->city ?? ''))]);

        return $parts ? implode(', ', array_map(trim(...), $parts)) : null;
    }

    private static function str(object $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
