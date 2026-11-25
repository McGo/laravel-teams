# Epic 03: Ausgang

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Ausgang“, „Karten und Links“
> Status: **offen**

## Beschreibung

Die Anwendung schreibt von sich aus: als Nachricht oder Karte im Bot-Chat und als Meldung im Aktivitätsfeed. Beides läuft über Laravel-Notifications. Für die Tests der Anwendung gibt es einen Fake.

## Tickets

### [E-03.01] BotClient
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Übernommen: App-Token vom eigenen Tenant mit Cache und Leeway, `createConversation`, `sendActivity`, `updateCard`
  - [ ] Neu: `sendText`, `sendCard` und `typing` gegen eine `TeamsConversation` oder eine Identität
  - [ ] Fehlende persönliche Konversation wird angelegt und gespeichert
  - [ ] Fehler als `TeamsRequestFailed` mit Status und Body, 429 als `TeamsThrottled` mit `Retry-After`

### [E-03.02] GraphClient
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Übernommen: App-Token, `sendActivityNotification`, `installAppForUser` mit RSC-Consent
  - [ ] Installation verlangt `TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All`; fehlt sie, nennt die Fehlermeldung genau diese Berechtigung

### [E-03.03] Notification-Kanäle
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `TeamsActivityChannel` mit `toTeamsActivity()` und `TeamsActivityMessage`, Installation im Zustellpfad, Sperre gegen Doppelversand über `teams_notifications`
  - [ ] `TeamsBotChannel` mit `toTeamsBot()`, liefert Text oder Karte
  - [ ] Bei `enabled = false` wird protokolliert und nichts verschickt
  - [ ] Notifiable ist jedes Model mit Identität, nicht fest `App\Models\User`

### [E-03.04] Cards und DeepLink
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `Cards`-Baukasten für Version 1.2: Text, Faktenliste, Knöpfe, Ergebniskarte
  - [ ] `Action.Execute` immer im `ActionSet` mit `fallback` auf `Action.Submit`
  - [ ] Keine deutschen Texte im Paket, Beschriftungen kommen von der Anwendung oder aus Übersetzungsdateien
  - [ ] `DeepLink::toTab()` akzeptiert nur Reiter aus `manifest.tabs`

### [E-03.05] Teams::fake()
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Ersetzt Bot- und Graph-Client, zeichnet ausgehende Aktivitäten und Feed-Meldungen auf
  - [ ] Assertions `assertMessageSent`, `assertCardSent`, `assertActivityNotified`, `assertNothingSent`
  - [ ] Helfer, um eine eingehende Aktivität ohne Token durch den Endpunkt zu schicken
