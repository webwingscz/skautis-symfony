<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Api;

use Webwings\SkautisBundle\Client\SkautisClientInterface;
use Webwings\SkautisBundle\Dto\Unit;

/**
 * Units (OrganizationUnit.asmx).
 */
final readonly class UnitApi
{
    private const SERVICE = 'OrganizationUnit';

    public function __construct(
        private SkautisClientInterface $client,
    ) {
    }

    public function detail(int $unitId): ?Unit
    {
        $row = $this->client->callOne(self::SERVICE, 'UnitDetail', ['ID' => $unitId]);

        return null !== $row ? Unit::fromApi($row) : null;
    }

    /**
     * Direct children of a unit - the oddíly under a středisko, for example.
     *
     * @return list<Unit>
     */
    public function children(int $unitId): array
    {
        $rows = $this->client->callAll(self::SERVICE, 'UnitAll', ['ID_UnitParent' => $unitId]);

        return array_map(Unit::fromApi(...), $rows);
    }
}
