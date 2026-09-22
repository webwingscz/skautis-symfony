<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Api;

use Webwings\SkautisBundle\Client\SkautisClientInterface;
use Webwings\SkautisBundle\Dto\Contact;
use Webwings\SkautisBundle\Dto\Person;

/**
 * Members and their contacts (OrganizationUnit.asmx).
 */
final readonly class PersonApi
{
    private const SERVICE = 'OrganizationUnit';

    public function __construct(
        private SkautisClientInterface $client,
    ) {
    }

    /**
     * Members of a unit. With $onlyDirect the members of child units are left out.
     *
     * @return list<Person>
     */
    public function listByUnit(int $unitId, bool $onlyDirect = false): array
    {
        $rows = $this->client->callAll(self::SERVICE, 'PersonAll', [
            'ID_Unit' => $unitId,
            'OnlyDirectMember' => $onlyDirect,
        ]);

        $people = array_map(Person::fromApi(...), $rows);

        usort($people, static fn (Person $a, Person $b): int => $a->displayName <=> $b->displayName);

        return $people;
    }

    public function detail(int $personId): ?Person
    {
        $row = $this->client->callOne(self::SERVICE, 'PersonDetail', ['ID' => $personId]);

        if (null === $row) {
            return null;
        }

        return Person::fromApi($row)->withContacts($this->contacts($personId));
    }

    /**
     * Contacts of the person, followed by their parents' contacts - for a child
     * member the parent's phone is usually the one you actually need.
     *
     * @return list<Contact>
     */
    public function contacts(int $personId): array
    {
        $own = array_map(
            static fn (object $row): Contact => Contact::fromApi($row),
            $this->client->callAll(self::SERVICE, 'PersonContactAll', ['ID_Person' => $personId]),
        );

        $parents = array_map(
            static fn (object $row): Contact => Contact::fromApi($row, isParent: true),
            $this->client->callAll(self::SERVICE, 'PersonContactAllParent', ['ID_Person' => $personId]),
        );

        return array_values(array_filter(
            [...$own, ...$parents],
            static fn (Contact $c): bool => '' !== $c->value,
        ));
    }
}
