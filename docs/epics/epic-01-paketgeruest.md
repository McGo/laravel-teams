# Epic 01: Paketgerüst

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Aufbau des Repos“, „Konfiguration“, „Tabellen“
> Status: **offen**

## Beschreibung

Das Composer-Paket ist installierbar, registriert sich per Auto-Discovery und bringt Konfiguration und Migrationen mit. Fachliches gibt es noch nicht. Am Ende läuft eine leere Testsuite in der CI grün.

## Tickets

### [E-01.01] composer.json und Service-Provider
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `composer.json` mit Name `mirkohaaser/teams`, Lizenz MIT, PHP `^8.3`, `illuminate/*` `^12.0|^13.0`, `firebase/php-jwt`
  - [ ] `laravel/sanctum` unter `suggest`, nicht unter `require`
  - [ ] Namespace `MirkoHaaser\Teams`, `TeamsServiceProvider` per `extra.laravel.providers`, Facade `Teams`
  - [ ] Dev: `orchestra/testbench`, `phpunit/phpunit`, Pint, PHPStan (Level 6)

### [E-01.02] Konfiguration
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `config/teams.php` mit den Werten aus dem Konzept: `enabled`, Tenant, Client, Secret, `allowed_tenants`, `app_id_uri`, `app_id`, `catalog_app_id`, `bot`, `graph`
  - [ ] Neu: `route_prefix` (Standard `api/teams`), `user_model`, `identity_resolver`, `session_issuer`, `unlinked_reply`, Abschnitt `manifest` mit Reitern und Activity-Types
  - [ ] Veröffentlichbar über `vendor:publish --tag=teams-config`
  - [ ] Kein Tenant- oder Host-Wert als Standard im Code

### [E-01.03] Migrationen und Modelle
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Migrationen für `teams_identities`, `teams_conversations`, `teams_notifications`, `teams_actions`, veröffentlichbar über `--tag=teams-migrations`, nicht automatisch geladen
  - [ ] Modelle `TeamsIdentity`, `TeamsConversation`, `TeamsNotification`, `TeamsAction` mit UUIDs
  - [ ] Eindeutiger Schlüssel `(tenant_id, aad_object_id)` an der Identität, `(service_url, conversation_id)` an der Konversation
  - [ ] Beziehung zum User über das konfigurierte Model
  - [ ] Scope `deliverable()` an der Identität (App installiert)

### [E-01.04] CI auf GitHub
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] GitHub Action mit Matrix PHP 8.3/8.4/8.5 × Laravel 12/13
  - [ ] Pint im Prüfmodus, PHPStan, PHPUnit
  - [ ] Badge im README
