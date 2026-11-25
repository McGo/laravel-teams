# Epic 03: Ausgang

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Ausgang“, „Karten und Links“
> Status: **erledigt bis auf E-03.05, letzter Punkt** (2026-11-25)

## Beschreibung

Die Anwendung schreibt von sich aus: als Nachricht oder Karte im Bot-Chat und als Meldung im Aktivitätsfeed. Beides läuft über Laravel-Notifications. Für die Tests der Anwendung gibt es einen Fake.

## Tickets

### [E-03.01] BotClient
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Übernommen: App-Token vom eigenen Tenant mit Cache und Leeway, `createConversation`, `sendActivity`, `updateCard`
  - [x] Neu: `reply()` an eine `TeamsConversation`, `send()` an eine Identität, `typing()`; Inhalt jeweils als `Reply` (Text oder Karte)
  - [x] Fehlende persönliche Konversation wird angelegt und gespeichert
  - [x] Fehler als `TeamsRequestFailed` mit Status und Body, 429 als `TeamsThrottled` mit `Retry-After`

### [E-03.02] GraphClient
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Übernommen: App-Token, `sendActivityNotification`, `installAppForUser` mit RSC-Consent
  - [x] Installation verlangt `TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All`; fehlt sie, nennt die Fehlermeldung genau diese Berechtigung

### [E-03.03] Notification-Kanäle
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `TeamsActivityChannel` mit `toTeamsActivity()` und `TeamsActivityMessage`, Installation im Zustellpfad, Sperre gegen Doppelversand über `teams_notifications`
  - [x] `TeamsBotChannel` mit `toTeamsBot()`, liefert Text oder Karte
  - [x] Bei `enabled = false` wird protokolliert und nichts verschickt
  - [x] Notifiable ist jedes Model mit Identität, nicht fest `App\Models\User`

### [E-03.04] Cards und DeepLink
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `Cards`-Baukasten für Version 1.2: Text, Faktenliste, Knöpfe, Ergebniskarte
  - [x] Knöpfe standardmäßig `Action.Submit` (Erfahrung der Ursprungsanwendung), `Cards::execute()` mit `fallback` auf `Action.Submit` und Version 1.4
  - [x] Keine deutschen Texte im Paket, Beschriftungen kommen von der Anwendung oder aus Übersetzungsdateien
  - [x] `DeepLink::toTab()` akzeptiert nur Reiter aus `manifest.tabs`

### [E-03.05] Teams::fake()
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Ersetzt Bot- und Graph-Client, zeichnet ausgehende Aktivitäten und Feed-Meldungen auf
  - [x] Assertions `assertMessageSent`, `assertCardSent`, `assertActivityNotified`, `assertNothingSent`
  - [ ] Helfer, um eine eingehende Aktivität ohne Token durch den Endpunkt zu schicken. Offen: bis dahin zeigt `tests/TestCase.php`, wie man mit eigenem Testschlüssel signiert
