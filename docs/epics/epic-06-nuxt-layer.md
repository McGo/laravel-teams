# Epic 06: Nuxt-Layer

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Der Nuxt-Layer“
> Status: **erledigt** (2026-11-25)

## Beschreibung

`nuxt-ms-teams` liefert alles, was eine Seite braucht, um als Reiter in Teams zu laufen. Die Reiter selbst baut die Anwendung.

## Tickets

### [E-06.01] Layer-Gerüst
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `nuxt/package.json` mit Name `nuxt-ms-teams`, Lizenz MIT, Peer `nuxt` ^4, Abhängigkeit `@microsoft/teams-js` ^2
  - [x] Keine Abhängigkeit zu Nuxt UI
  - [x] Runtime-Config `teams.ssoEndpoint` und `teams.apiBase`

### [E-06.02] useTeams()
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Übernommen und von Anwendungs-Typen gelöst: Initialisierung, SSO-Token holen und tauschen, Wiederholung nach Consent (3 Versuche, 1 s Pause), `subPageId`, Theme
  - [x] Theme `dark` und `contrast` setzen den Color-Mode, wenn `@nuxtjs/color-mode` vorhanden ist
  - [x] Typ des Users generisch (`useTeams<User>()`)
  - [x] Diagnosezeile mit Host, Client-Typ und letztem Fehler
  - [x] Außerhalb von Teams klare Meldung statt endlosem Laden

### [E-06.03] Layout, Consent-Seite, CSP
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Layout `teams` ohne Navigation
  - [x] Seite `/teams/consent`, die den Consent-Dialog abschließt
  - [x] Route-Rule für `/teams/**` mit `frame-ancestors` für die Teams-, Microsoft-365- und Outlook-Hosts
  - [x] `/teams/**` client-only, damit SSG-Anwendungen den Layer nutzen können

### [E-06.04] Spielwiese
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `nuxt/playground` mit zwei Beispielreitern, lauffähig über einen Dev-Tunnel
  - [x] Anleitung im Guide
