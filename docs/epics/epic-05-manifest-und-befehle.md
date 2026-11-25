# Epic 05: Manifest und Artisan-Befehle

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Konfiguration“, „Befehle“
> Status: **offen**

## Beschreibung

Das Manifest entsteht aus der Config, nicht aus einer Datei mit Platzhaltern. Dazu kommen die Befehle, die man bei Einrichtung und Fehlersuche braucht.

## Tickets

### [E-05.01] teams:manifest
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Erzeugt `manifest.json` in Schema-Version 1.29 aus `config('teams.manifest')`: `staticTabs` (Scope `personal`), `bots`, `webApplicationInfo`, `activities.activityTypes`, RSC `TeamsActivity.Send.User`, `validDomains`
  - [ ] Ohne Bot-App-ID kein `bots`-Eintrag, ohne Activity-Types kein RSC
  - [ ] `resource` in der Form `api://{host}/botid-{client-id}`, sobald ein Bot konfiguriert ist
  - [ ] Prüft Grenzen vor dem Schreiben: höchstens ein Bot, `templateText` höchstens 128 Zeichen, Icons 192×192 und 32×32
  - [ ] Packt Manifest und Icons nach `storage/app/teams/{name}-{version}.zip`

### [E-05.02] teams:install und teams:link
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `teams:install {user}` installiert per Graph mit RSC-Consent und setzt `installed_at`
  - [ ] `teams:install --all` für alle zugeordneten Identitäten, mit Zusammenfassung am Ende
  - [ ] `teams:link {user} {upn}` legt eine Identität von Hand an (Object-ID per Graph nachgeschlagen)

### [E-05.03] teams:check und teams:send-test
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `teams:check` prüft je Punkt mit eigener Zeile: Config vollständig, Graph-Token holbar, Bot-Token holbar, Katalog-ID gesetzt, Manifest baubar
  - [ ] Fehlschläge nennen die wahrscheinliche Ursache aus dem Fallstrick-Journal
  - [ ] `teams:send-test {user}` schickt eine Testkarte in den Bot-Chat und eine Meldung in den Aktivitätsfeed
