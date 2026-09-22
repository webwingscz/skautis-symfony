<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Dto;

/**
 * A member (OrganizationUnit.PersonAll / PersonDetail).
 */
final readonly class Person
{
    /**
     * @param list<Contact> $contacts
     */
    public function __construct(
        public int $id,
        public string $displayName,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $nickName,
        public ?\DateTimeImmutable $birthday,
        public ?string $street,
        public ?string $city,
        public ?string $postcode,
        public ?string $sex,
        public ?string $identificationCode,
        public array $contacts = [],
    ) {
    }

    public static function fromApi(object $row): self
    {
        return new self(
            id: (int) ($row->ID ?? 0),
            displayName: (string) ($row->DisplayName ?? $row->ListName ?? '?'),
            firstName: self::str($row, 'FirstName'),
            lastName: self::str($row, 'LastName'),
            nickName: self::str($row, 'NickName'),
            birthday: self::date($row, 'Birthday'),
            street: self::str($row, 'Street'),
            city: self::str($row, 'City'),
            postcode: self::str($row, 'Postcode'),
            sex: self::str($row, 'Sex'),
            identificationCode: self::str($row, 'IdentificationCode'),
        );
    }

    /**
     * @param list<Contact> $contacts
     */
    public function withContacts(array $contacts): self
    {
        return new self(
            $this->id,
            $this->displayName,
            $this->firstName,
            $this->lastName,
            $this->nickName,
            $this->birthday,
            $this->street,
            $this->city,
            $this->postcode,
            $this->sex,
            $this->identificationCode,
            $contacts,
        );
    }

    /**
     * Name as skautIS lists it, with the nickname when there is one.
     */
    public function label(): string
    {
        if (null !== $this->nickName) {
            return sprintf('%s (%s)', $this->displayName, $this->nickName);
        }

        return $this->displayName;
    }

    public function initials(): string
    {
        $letters = array_map(
            static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
            array_filter([$this->firstName, $this->lastName]),
        );

        return $letters ? implode('', $letters) : mb_strtoupper(mb_substr($this->displayName, 0, 1));
    }

    public function age(?\DateTimeImmutable $now = null): ?int
    {
        return $this->birthday?->diff($now ?? new \DateTimeImmutable())->y;
    }

    /**
     * @return list<Contact>
     */
    public function phones(): array
    {
        return array_values(array_filter($this->contacts, static fn (Contact $c): bool => $c->isPhone()));
    }

    /**
     * @return list<Contact>
     */
    public function emails(): array
    {
        return array_values(array_filter($this->contacts, static fn (Contact $c): bool => $c->isEmail()));
    }

    private static function str(object $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }

    private static function date(object $row, string $field): ?\DateTimeImmutable
    {
        $value = self::str($row, $field);

        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
