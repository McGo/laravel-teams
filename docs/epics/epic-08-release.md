# Epic 08: Veröffentlichung

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Lizenz und Veröffentlichung“
> Status: **in Arbeit**

## Beschreibung

Ein Git-Tag veröffentlicht beide Pakete mit derselben Version.

## Tickets

### [E-08.01] Packagist
- **Status:** offen
- **Acceptance Criteria:**
  - [x] Paket `mirkohaaser/teams` auf Packagist angelegt (2026-11-25)
  - [x] GitHub-Hook aktiv (`packagist.org/api/github` im Repo eingetragen)
  - [ ] `composer require mirkohaaser/teams` in einer frischen Laravel-Anwendung erfolgreich

### [E-08.02] npm
- **Status:** offen
- **Acceptance Criteria:**
  - [x] Name `nuxt-ms-teams` ohne Scope unter dem npm-Konto `mirko-haaser`, wie `nuxt-ui-basekit`
  - [x] GitHub Action `release.yml` veröffentlicht `nuxt/` bei Tag `v*` mit der Tag-Version, Provenance an
  - [ ] Trusted Publisher auf npmjs.com eingetragen (Repo `McGo/laravel-teams`, Workflow `release.yml`), kein Token, wie beim BaseKit. Setzt voraus, dass `nuxt-ms-teams` einmal von Hand veröffentlicht ist

### [E-08.03] Erste Version
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `CHANGELOG.md` nach Keep a Changelog
  - [ ] `v0.1.0` erst, wenn Epic 09 durch ist, also die Ursprungsanwendung auf dem Paket läuft
  - [ ] `v1.0.0` nach der zweiten Anwendung
