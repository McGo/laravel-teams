# Epic 08: Veröffentlichung

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Lizenz und Veröffentlichung“
> Status: **offen**

## Beschreibung

Ein Git-Tag veröffentlicht beide Pakete mit derselben Version.

## Tickets

### [E-08.01] Packagist
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Paket `mirkohaaser/teams` auf Packagist angelegt, GitHub-Hook aktiv
  - [ ] `composer require mirkohaaser/teams` in einer frischen Laravel-Anwendung erfolgreich

### [E-08.02] npm
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Scope `@mirkohaaser` auf npm geklärt (Nutzer oder Organisation)
  - [ ] GitHub Action veröffentlicht `nuxt/` bei Tag `v*` mit der Tag-Version, Provenance an
  - [ ] npm-Token als Repository-Secret, nicht im Workflow

### [E-08.03] Erste Version
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `CHANGELOG.md` nach Keep a Changelog
  - [ ] `v0.1.0` erst, wenn Epic 09 durch ist, also die Ursprungsanwendung auf dem Paket läuft
  - [ ] `v1.0.0` nach der zweiten Anwendung
