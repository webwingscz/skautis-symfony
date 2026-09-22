<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Webwings\SkautisBundle\Dto\Contact;
use Webwings\SkautisBundle\Dto\Person;

final class PersonTest extends TestCase
{
    public function testMapsTheFieldsSkautisActuallyReturns(): void
    {
        $person = Person::fromApi((object) [
            'ID' => 42,
            'DisplayName' => 'Nováková Jana',
            'FirstName' => 'Jana',
            'LastName' => 'Nováková',
            'NickName' => 'Sýkorka',
            'Birthday' => '1989-04-17T00:00:00',
        ]);

        self::assertSame(42, $person->id);
        self::assertSame('Nováková Jana (Sýkorka)', $person->label());
        self::assertSame('JN', $person->initials());
        self::assertSame(36, $person->age(new \DateTimeImmutable('2026-01-01')));
    }

    public function testEmptyStringsBecomeNull(): void
    {
        $person = Person::fromApi((object) ['ID' => 1, 'DisplayName' => 'Kdosi', 'NickName' => '']);

        self::assertNull($person->nickName);
        self::assertSame('Kdosi', $person->label());
    }

    public function testContactsAreSplitIntoPhonesAndEmails(): void
    {
        $person = Person::fromApi((object) ['ID' => 1, 'DisplayName' => 'Kdosi'])->withContacts([
            Contact::fromApi((object) ['ID' => 1, 'ID_ContactType' => 'telefon', 'Value' => '+420 777 123 456']),
            Contact::fromApi((object) ['ID' => 2, 'ID_ContactType' => 'email', 'Value' => 'a@b.cz'], isParent: true),
        ]);

        self::assertCount(1, $person->phones());
        self::assertCount(1, $person->emails());
        self::assertSame('+420777123456', $person->phones()[0]->dialable());
        self::assertTrue($person->emails()[0]->isParent);
    }
}
