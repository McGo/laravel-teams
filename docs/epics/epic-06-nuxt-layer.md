# Epic 06: Nuxt-Layer

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Der Nuxt-Layer“
> Status: **offen**

## Beschreibung

`@mirkohaaser/nuxt-teams` liefert alles, was eine Seite braucht, um als Reiter in Teams zu laufen. Die Reiter selbst baut die Anwendung.

## Tickets

### [E-06.01] Layer-Gerüst
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `nuxt/package.json` mit Name `@mirkohaaser/nuxt-teams`, Lizenz MIT, Peer `nuxt` ^4, Abhängigkeit `@microsoft/teams-js` ^2
  - [ ] Keine Abhängigkeit zu Nuxt UI
  - [ ] Runtime-Config `teams.ssoEndpoint` und `teams.apiBase`

### [E-06.02] useTeams()
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Übernommen und von Anwendungs-Typen gelöst: Initialisierung, SSO-Token holen und tauschen, Wiederholung nach Consent (3 Versuche, 1 s Pause), `subPageId`, Theme
  - [ ] Theme `dark` und `contrast` setzen den Color-Mode, wenn `@nuxtjs/color-mode` vorhanden ist
  - [ ] Typ des Users generisch (`useTeams<User>()`)
  - [ ] Diagnosezeile mit Host, Client-Typ und letztem Fehler
  - [ ] Außerhalb von Teams klare Meldung statt endlosem Laden

### [E-06.03] Layout, Consent-Seite, CSP
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Layout `teams` ohne Navigation
  - [ ] Seite `/teams/consent`, die den Consent-Dialog abschließt
  - [ ] Route-Rule für `/teams/**` mit `frame-ancestors` für die Teams-, Microsoft-365- und Outlook-Hosts
  - [ ] `/teams/**` client-only, damit SSG-Anwendungen den Layer nutzen können

### [E-06.04] Spielwiese
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `nuxt/playground` mit zwei Beispielreitern, lauffähig über einen Dev-Tunnel
  - [ ] Anleitung im Guide
