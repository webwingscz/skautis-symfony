# Změny

Formát vychází z [Keep a Changelog](https://keepachangelog.com/cs/1.1.0/),
verzování ze [SemVer](https://semver.org/lang/cs/).

## [Nevydáno]

## [0.1.0]

První vydání.

### Přidáno

- Typovaný SOAP klient nad webovými službami skautISu (`Client\SoapSkautisClient`)
  s překladem SOAP faultů na `SkautisAuthenticationException`,
  `SkautisPermissionException` a `SkautisTransportException`.
- `Api\UserApi`, `Api\UnitApi`, `Api\PersonApi` s ověřenými názvy parametrů.
- DTO `Person`, `Unit`, `Role`, `Contact`.
- Přihlašování skautISovým účtem: `Security\SkautisAuthenticator`,
  `SkautisUser`, `SkautisUserProvider` a přepínání rolí.
- Automatické prodlužování 30minutového přihlášení a překlad vypršelého tokenu
  na přesměrování místo chyby 500.
- `Client\FixtureSkautisClient` a ukázková data pro vývoj bez přiděleného AppID.
