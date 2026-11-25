# Epic 02: Bot-Eingang

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Eingang: der Messaging-Endpunkt“
> Status: **erledigt** (2026-11-25)

## Beschreibung

Teams schickt Aktivitäten an den Messaging-Endpunkt. Das Paket prüft sie, merkt sich die Konversation und gibt sie an die Handler weiter, die eine Anwendung angemeldet hat. Token-Prüfung und Kartenaktionen werden aus der Ursprungsanwendung übernommen, Textnachrichten und die Registry kommen neu dazu.

## Tickets

### [E-02.01] Token-Prüfung für den Bot-Connector
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `BotFrameworkTokenValidator` übernommen: JWKS aus den OpenID-Metadaten von `login.botframework.com`, Cache 24 h, Leeway 5 min
  - [x] Geprüft werden Aussteller, Audience gleich Bot-App-ID, `serviceUrl` gegen den Claim und das Endorsement `msteams`
  - [x] Tests mit selbst signierten Tokens für jeden Ablehnungsgrund

### [E-02.02] Endpunkt und Konversationen
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `POST {präfix}/bot/messages`, 404 ohne Bot-App-ID, 401 bei ungültigem Token
  - [x] Jede Aktivität aktualisiert `teams_conversations` (Art aus `conversation.conversationType`)
  - [x] Identität über `from.aadObjectId` und `tenant.id` gefunden, `last_seen_at` gesetzt
  - [x] Activity als unveränderliches DTO (`IncomingActivity`) statt rohem Array

### [E-02.03] Registry für Handler
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `Teams::onMessage()`, `Teams::command()`, `Teams::action()` nehmen Klassennamen an, aufgelöst über den Container
  - [x] Interfaces `HandlesMessage`, `HandlesCommand`, `HandlesCardAction`
  - [x] Ein doppelt angemeldeter Befehl oder Verb wirft beim Booten eine Exception
  - [x] `@Botname`-Erwähnungen werden vor dem Routing aus dem Text entfernt

### [E-02.04] Textnachrichten über die Queue
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Endpunkt bestätigt sofort mit 200 und schickt `HandleIncomingMessage` in die Queue (Verbindung und Queue konfigurierbar)
  - [x] Erstes Wort wählt den Befehl, sonst allgemeiner Handler, sonst Hilfe mit der Liste der Befehle
  - [x] Antwort des Handlers (`Reply` mit Text oder Karte) geht über den `BotClient` in dieselbe Konversation
  - [x] Optional: Tipp-Indikator (`typing`), bevor der Handler läuft
  - [x] Eintrag in `teams_actions` mit Befehl, Dauer und Ergebnis

### [E-02.05] Kartenaktionen
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `Action.Execute` (Invoke `adaptiveCard/action`) gibt die Antwortkarte im Invoke-Response zurück
  - [x] `Action.Submit` (Message mit Karten-Nutzlast) ersetzt die Originalkarte über `replyToId`, ohne Bezug als neue Nachricht
  - [x] Unbekanntes Verb ergibt eine Fehlerkarte, keine Exception nach außen
  - [x] Eintrag in `teams_actions`

### [E-02.06] Installation und unbekannte Absender
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Events `BotInstalled` und `BotUninstalled` aus `installationUpdate` bzw. `conversationUpdate`, `installed_at` gepflegt
  - [x] Absender ohne Zuordnung: kein Handler, Antwort aus `unlinked_reply` (Standard: Hinweis mit Deep Link in den ersten Reiter)
  - [x] Es wird nie ein User angelegt
