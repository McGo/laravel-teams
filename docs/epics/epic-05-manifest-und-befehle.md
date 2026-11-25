# Epic 05: Manifest und Artisan-Befehle

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Konfiguration“, „Befehle“
> Status: **erledigt** (2026-11-25)

## Beschreibung

Das Manifest entsteht aus der Config, nicht aus einer Datei mit Platzhaltern. Dazu kommen die Befehle, die man bei Einrichtung und Fehlersuche braucht.

## Tickets

### [E-05.01] teams:manifest
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Erzeugt `manifest.json` in Schema-Version 1.29 aus `config('teams.manifest')`: `staticTabs` (Scope `personal`), `bots`, `webApplicationInfo`, `activities.activityTypes`, RSC `TeamsActivity.Send.User`, `validDomains`
  - [x] Ohne Bot-App-ID kein `bots`-Eintrag, ohne Activity-Types kein RSC
  - [x] `resource` in der Form `api://{host}/botid-{client-id}`, sobald ein Bot konfiguriert ist
  - [x] Prüft Grenzen vor dem Schreiben: höchstens ein Bot, `templateText` höchstens 128 Zeichen, Icons 192×192 und 32×32
  - [x] Packt Manifest und Icons nach `storage/app/teams/{name}-{version}.zip`

### [E-05.02] teams:install und teams:link
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `teams:install {user}` installiert per Graph mit RSC-Consent und setzt `installed_at`
  - [x] `teams:install --all` für alle zugeordneten Identitäten, mit Zusammenfassung am Ende
  - [x] `teams:link {user} {upn}` legt eine Identität von Hand an (Object-ID per Graph nachgeschlagen)

### [E-05.03] teams:check und teams:send-test
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `teams:check` prüft je Punkt mit eigener Zeile: Config vollständig, Graph-Token holbar, Bot-Token holbar, Katalog-ID gesetzt, Manifest baubar
  - [x] Fehlschläge nennen die wahrscheinliche Ursache aus dem Fallstrick-Journal
  - [x] `teams:send-test {user}` schickt eine Testkarte in den Bot-Chat und eine Meldung in den Aktivitätsfeed
