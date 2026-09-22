<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Dto;

/**
 * One role the logged-in user can act in (UserManagement.UserRoleAll).
 */
final readonly class Role
{
    public function __construct(
        public int $id,
        public int $roleTypeId,
        public string $name,
        public ?int $unitId,
        public ?string $unitName,
        public ?string $registrationNumber,
        public bool $active,
    ) {
    }

    public static function fromApi(object $row): self
    {
        return new self(
            id: (int) ($row->ID ?? 0),
            roleTypeId: (int) ($row->ID_Role ?? 0),
            name: (string) ($row->Role ?? $row->DisplayName ?? '?'),
            unitId: isset($row->ID_Unit) ? (int) $row->ID_Unit : null,
            unitName: isset($row->Unit) ? (string) $row->Unit : null,
            registrationNumber: isset($row->RegistrationNumber) ? (string) $row->RegistrationNumber : null,
            active: (bool) ($row->IsActive ?? false),
        );
    }

    public function label(): string
    {
        return null !== $this->unitName && '' !== $this->unitName
            ? sprintf('%s – %s', $this->name, $this->unitName)
            : $this->name;
    }
}
