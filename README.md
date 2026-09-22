# WebwingsSkautisBundle

Symfony bundle pro webové služby [skautISu](https://is.skaut.cz) — informačního
systému Junáka.

Dává vám typovaný klient nad jejich SOAP API, hotové přihlašování skautISovým
účtem a přepínání rolí. A taky režim, ve kterém celá aplikace funguje bez
přiděleného AppID, takže se dá vyvíjet dřív, než vám ho správce schválí.

```php
final class TeamController extends AbstractController
{
    #[Route('/oddil')]
    public function index(PersonApi $people, #[CurrentUser] SkautisUser $user): Response
    {
        return $this->render('team.html.twig', [
            'members' => $people->listByUnit($user->getUnitId()),
        ]);
    }
}
```

## Proč ne `skautis/skautis`

[`skautis/skautis`](https://packagist.org/packages/skautis/skautis) je funkční a
běží v produkci — pokud vám vyhovuje jeho tvar, použijte ho. Deklaruje ale
`php >=5.6` a celé API jede přes `__call`, takže statická analýza o něm neví nic
(jejich [issue #102](https://github.com/skaut/Skautis/issues/102)). Nemá PSR-3
logování ([#70](https://github.com/skaut/Skautis/issues/70)) a integraci má jen
na Nette — `skautis/skautis-bundle`, na který odkazuje jejich `composer.json`,
na Packagistu neexistuje.

Tenhle bundle je menší a užší: typované DTO, PSR-3, Symfony DI a security.

## Instalace

```bash
composer require webwingscz/skautis-symfony
```

Bez Symfony Flex zaregistrujte bundle ručně v `config/bundles.php`:

```php
Webwings\SkautisBundle\WebwingsSkautisBundle::class => ['all' => true],
```

## Konfigurace

```yaml
# config/packages/webwings_skautis.yaml
webwings_skautis:
    base_url: 'https://test-is.skaut.cz'   # ostrý provoz: https://is.skaut.cz
    app_id: '%env(SKAUTIS_APP_ID)%'
    mock: false                            # true = odpovídat z fixtures
    timeout: 30                            # timeout SOAP volání v sekundách
    # fixture_dir: '%kernel.project_dir%/fixtures/skautis'
    security:
        login_route: app_login             # sem skautIS posílá POST s tokenem
        success_route: app_dashboard       # kam po přihlášení
        logout_route: app_logout
        refresh_interval: 600              # jak často nejvýš prodlužovat přihlášení
```

## Přihlašování

skautIS řeší autentizaci sám. Uživatel odejde na `{base_url}/Login/?appid=…`,
přihlásí se tam a skautIS pošle **POST** na adresu, kterou jste u AppID
zaregistrovali — pole `skautIS_Token`, `skautIS_IDRole`, `skautIS_IDUnit`
a `skautIS_DateLogout`.

```yaml
# config/packages/security.yaml
security:
    providers:
        skautis:
            id: Webwings\SkautisBundle\Security\SkautisUserProvider
    firewalls:
        main:
            lazy: true
            provider: skautis
            custom_authenticators:
                - Webwings\SkautisBundle\Security\SkautisAuthenticator
            entry_point: Webwings\SkautisBundle\Security\SkautisAuthenticator
            logout:
                path: app_logout
                target: app_logged_out
```

Vaše routy musí odpovídat tomu, co máte zaregistrované ve skautISu:

```php
#[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]   // POST bere authenticator
#[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])] // obsluhuje firewall
```

Odhlášení musí proběhnout i na straně skautISu, jinak se příští návštěva přihlásí
sama — přesměrujte na `SkautisConfig::logoutUrl($token)`, skautIS pak vrátí
uživatele na vaši `logout_route`.

Token platí 30 minut. Bundle ho na pozadí prodlužuje (`LoginUpdateRefresh`,
nejvýš jednou za `refresh_interval`) a když vyprší, pošle uživatele na odhlášení
místo toho, aby spadla pětistovka.

## Co je k dispozici

| Služba | K čemu |
|---|---|
| `Api\UserApi` | detail účtu, seznam rolí, přepnutí role, prodloužení přihlášení |
| `Api\UnitApi` | detail jednotky, podřízené jednotky |
| `Api\PersonApi` | členové jednotky, detail osoby, kontakty včetně rodičovských |
| `Client\SkautisClientInterface` | cokoli dalšího: `callAll()` / `callOne()` |
| `SkautisConfig` | sestavení přihlašovací a odhlašovací URL |

Chyby chodí jako `Exception\SkautisAuthenticationException` (vypršelý token),
`SkautisPermissionException` (role nemá právo) a `SkautisTransportException`
(skautIS neodpovídá) — všechny dědí z `SkautisException`.

## Vývoj bez AppID

```yaml
webwings_skautis:
    mock: true
```

`SkautisClientInterface` se přepne na `FixtureSkautisClient`, který odpovídá
z JSON souborů. Bundle veze ukázkovou sadu (středisko, dva oddíly, dvacet členů
s kontakty); vlastní data nasměrujete přes `fixture_dir`.

Přihlášení projde stejnou cestou jako doopravdy — pošlete na svou login routu POST
s `skautIS_Token` rovným `FixtureSkautisClient::TOKEN`. Díky tomu se testuje
skutečný authenticator, ne jeho obejití.

## Přidání další metody skautISu

Přehled služeb je na `https://test-is.skaut.cz/JunakWebservice`, živě se dá
zkoušet na [ws.skautis.cz](https://ws.skautis.cz/).

**Názvy vstupních polí vždy ověřte** na `…/JunakWebservice/<Služba>.asmx?op=<Metoda>` —
stránka ukazuje request i response XML. Odhadovat je nemá cenu: `SoapClient`
pracuje podle WSDL a neznámé klíče tiše zahodí, takže metoda jen vrátí prázdno.

```php
$rows = $client->callAll('OrganizationUnit', 'PersonAll', ['ID_Unit' => 1002]);
$one  = $client->callOne('OrganizationUnit', 'UnitDetail', ['ID' => 1002]);
```

Token do `ID_Login` a AppID do `ID_Application` doplňuje klient sám. Pozor na
dvě výjimky: `LoginUpdate` a `LoginUpdateRefresh` berou token jako `ID`.

## Požadavky

PHP 8.2+ s `ext-soap`, Symfony 6.4 / 7.x / 8.x.

Rozsah je deklarovaný podle použitého API; **odzkoušeno zatím jen na PHP 8.4
a Symfony 8.1**. Pokud narazíte na starší kombinaci, dejte vědět.

## Licence

MIT, viz [LICENSE](LICENSE).
