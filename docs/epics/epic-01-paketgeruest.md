# Epic 01: Paketgerüst

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Aufbau des Repos“, „Konfiguration“, „Tabellen“
> Status: **erledigt** (2026-11-25)

## Beschreibung

Das Composer-Paket ist installierbar, registriert sich per Auto-Discovery und bringt Konfiguration und Migrationen mit. Fachliches gibt es noch nicht. Am Ende läuft eine leere Testsuite in der CI grün.

## Tickets

### [E-01.01] composer.json und Service-Provider
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `composer.json` mit Name `mirkohaaser/teams`, Lizenz MIT, PHP `^8.3`, `laravel/framework` `^12.0|^13.0` (statt einzelner `illuminate/*`, weil Jobs und Events Foundation-Klassen brauchen), `firebase/php-jwt`
  - [x] `laravel/sanctum` unter `suggest`, nicht unter `require`
  - [x] Namespace `MirkoHaaser\Teams`, `TeamsServiceProvider` per `extra.laravel.providers`, Facade `Teams`
  - [x] Dev: `orchestra/testbench`, `phpunit/phpunit`, Pint, PHPStan (Level 6)

### [E-01.02] Konfiguration
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `config/teams.php` mit den Werten aus dem Konzept: `enabled`, Tenant, Client, Secret, `allowed_tenants`, `app_id_uri`, `app_id`, `catalog_app_id`, `bot`, `graph`
  - [x] Neu: `routes.prefix` (Standard `api/teams`), `user_model`, `user_key_type`, `identity_resolver`, `session_issuer`, `bot.unlinked_reply`, Abschnitt `manifest` mit Reitern und Activity-Types
  - [x] Veröffentlichbar über `vendor:publish --tag=teams-config`
  - [x] Kein Tenant- oder Host-Wert als Standard im Code

### [E-01.03] Migrationen und Modelle
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Migrationen für `teams_identities`, `teams_conversations`, `teams_notifications`, `teams_actions`, veröffentlichbar über `--tag=teams-migrations`, nicht automatisch geladen
  - [x] Modelle `TeamsIdentity`, `TeamsConversation`, `TeamsNotification`, `TeamsAction` mit UUIDs
  - [x] Eindeutiger Schlüssel `(tenant_id, aad_object_id)` an der Identität, `(service_url, conversation_id)` an der Konversation
  - [x] Beziehung zum User über das konfigurierte Model
  - [x] Scope `deliverable()` an der Identität (App installiert)

### [E-01.04] CI auf GitHub
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] GitHub Action mit Matrix PHP 8.3/8.4/8.5 × Laravel 12/13
  - [x] Pint im Prüfmodus, PHPStan, PHPUnit
  - [x] Badge im README
