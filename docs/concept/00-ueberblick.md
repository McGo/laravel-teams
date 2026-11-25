# 00 — Überblick: mirkohaaser/teams

Status: Entwurf (2026-11-25)

Das Paket bindet eine Laravel-Anwendung mit Nuxt-Oberfläche an Microsoft Teams an. Eine Anwendung bekommt damit eine eigene Teams-App mit einem Bot, der Nachrichten und Kartenaktionen entgegennimmt und von sich aus schreibt, mit Benachrichtigungen im Aktivitätsfeed und mit einem Symbol in der linken Teams-Leiste, hinter dem mehrere Reiter liegen.

Der Code stammt aus einer Anwendung, in der Bot, Activity Feed und Personal Tab seit Juli 2026 produktiv laufen. Er wird herausgelöst, von allem Fachlichen befreit und als Paket weitergeführt. Neu geschrieben wird nur, was die erste Anwendung nicht brauchte: freie Textnachrichten an den Bot und eine Registry, über die eine Anwendung ihre eigenen Befehle und Aktionen anmeldet.

## Abnehmer

Drei Anwendungen sollen das Paket nutzen. Zwei davon laufen in eigenen Tenants, eine im Tenant eines Kunden. Daraus folgt die wichtigste Randbedingung: Eine Installation des Pakets gehört zu genau einer Entra-App-Registrierung und genau einer Teams-App, und die werden im Tenant der Nutzer angelegt. Geteilt wird der Code, nie eine Registrierung.

## Abgrenzung

Nicht Teil des Pakets:

- Posten in Kanäle über Workflows oder Power Automate. Die Office-365-Connectors sind seit Mai 2026 abgeschaltet, und ein Flow erscheint unter fremder Identität ohne Rückweg in die Anwendung.
- Graph-Chatnachrichten im Namen eines Nutzers. App-only ist dieser Endpunkt nur für Migrationen freigegeben.
- Multi-Tenant-Bots. Microsoft legt seit dem 31.07.2025 keine neuen an. Das Paket kennt nur Single-Tenant-Registrierungen.
- Sprachverständnis. Der Bot reicht Text an einen Handler der Anwendung weiter. Ob dahinter ein Befehlsparser oder ein Sprachmodell steckt, entscheidet die Anwendung.
- Fachliche Karten und Tab-Inhalte. Das Paket liefert Baukasten und Zustellung, die Anwendung den Inhalt.

## Aufbau des Repos

Ein Repo, zwei Pakete mit gemeinsamer Versionsnummer.

```
laravel-teams/
├── composer.json        mirkohaaser/teams (Packagist braucht es im Wurzelordner)
├── src/                 Laravel-Paket, Namespace MirkoHaaser\Teams
├── config/teams.php
├── database/migrations/
├── tests/
├── nuxt/                Nuxt-Layer @mirkohaaser/nuxt-teams (npm)
└── docs/
    ├── concept/         Konzepte (deutsch)
    ├── epics/
    └── guide/           Einrichtung und Fallstricke (englisch, öffentlich)
```

Die Nuxt-Seite bekommt den Scope `@mirkohaaser` ohne Bindestrich. `@mirko-haaser` ist in den Anwendungen per `.npmrc` auf die private GitLab-Registry des BaseKits umgeleitet. Ein öffentliches Paket im selben Scope würde dort gesucht und nicht gefunden.

Über `.gitattributes` bleiben `nuxt/`, `docs/` und `tests/` aus dem Composer-Archiv heraus.

## Das Laravel-Paket

### Konfiguration

`config/teams.php` wird veröffentlicht und liest alles aus der Umgebung. Im Code steht keine Tenant-ID. Die Werte entsprechen dem produktiven Stand: Hauptschalter `enabled`, Tenant, Client-ID und Secret der Entra-App, eine Liste erlaubter Tenants für eingehende Nutzer-Token, App-ID-URI, Manifest-ID und Katalog-ID der Teams-App, Bot-App-ID und Passwort, Graph-Zeitlimits.

Neu dazu kommen der Routen-Präfix, die Klasse des User-Models und ein Abschnitt `manifest`. Dort beschreibt die Anwendung ihre Teams-App: Name, Beschreibung, Herausgeber, Farben, die Reiter mit `entityId`, Titel und Pfad sowie die Activity-Types mit ihrem `templateText`. Aus diesem Abschnitt baut `teams:manifest` das ZIP für den Upload. Das Manifest wird damit nicht mehr von Hand mit Platzhaltern gepflegt, und der Deep-Link-Helper prüft gegen dieselbe Liste von Reitern.

Ist `enabled` aus, laufen Kanäle, Befehle und Endpunkte folgenlos durch. Der Messaging-Endpunkt antwortet ohne Bot-App-ID mit 404.

### Tabellen

| Tabelle | Inhalt |
|---|---|
| `teams_identities` | Entra-Nutzer zu Anwendungs-User: `tenant_id`, `aad_object_id`, `upn`, `user_id`, `installed_at`, `last_seen_at` |
| `teams_conversations` | Konversationsreferenz: `service_url`, `conversation_id`, Art (`personal`, `groupChat`, `channel`), optional die Identität |
| `teams_notifications` | Protokoll und Sperre gegen Doppelversand im Aktivitätsfeed |
| `teams_actions` | Protokoll ausgeführter Befehle und Kartenaktionen |

In der Ursprungsanwendung hängt die Konversation als zwei Spalten an der Identität. Das reicht für den persönlichen Chat, aber nicht für einen Bot, der in einem Gruppenchat oder Kanal angesprochen wird. Die eigene Tabelle kostet jetzt eine Migration und erspart später einen Umbau.

Die Migrationen werden veröffentlicht, nicht automatisch geladen. Eine Anwendung mit vorhandenen Tabellen übernimmt sie über eine eigene Überleitungs-Migration (siehe Umstieg).

### Eingang: der Messaging-Endpunkt

`POST {präfix}/bot/messages` nimmt jede Aktivität des Bot-Connectors an. Der Controller prüft das Token (JWKS aus den OpenID-Metadaten von `login.botframework.com`, Aussteller `api.botframework.com`, Audience gleich Bot-App-ID, `serviceUrl` gegen den Claim, Endorsement `msteams`), merkt sich die Konversation aus jeder Aktivität und verteilt dann nach Art:

| Aktivität | Weiter an |
|---|---|
| `installationUpdate`, `conversationUpdate` | Event `BotInstalled` bzw. `BotUninstalled` |
| `message` mit Text | `MessageHandler` der Anwendung |
| `message` mit Karten-Nutzlast (`Action.Submit`) | Aktion aus der Registry, Karte wird ersetzt |
| `invoke` `adaptiveCard/action` (`Action.Execute`) | Aktion aus der Registry, Antwortkarte im Invoke-Response |

Die Anwendung meldet ihre Handler in einem Service-Provider an:

```php
Teams::onMessage(EingangHandler::class);
Teams::command('zeiten', ZeitenBefehl::class);
Teams::action('timer.stoppen', TimerStoppen::class);
```

`command()` ist ein dünner Router über `onMessage()`: Das erste Wort der Nachricht wählt den Befehl. Was keinen Befehl trifft, geht an den allgemeinen Handler oder bekommt eine Hilfe-Antwort mit der Liste der Befehle.

Textnachrichten laufen über die Queue. Der Endpunkt bestätigt sofort mit 200, der Job ruft den Handler auf und antwortet über den `BotClient`. Ein Handler darf damit langsam sein, etwa wenn er ein Sprachmodell fragt. Kartenaktionen laufen synchron, weil die Antwortkarte die alte an Ort und Stelle ersetzt und Teams darauf nur kurz wartet. Eine Aktion, die länger braucht, gibt sofort eine Zwischenkarte zurück und schickt das Ergebnis per Job nach.

Ist der Absender keinem Anwendungs-User zugeordnet, ruft das Paket keinen Handler auf. Es antwortet mit einem konfigurierbaren Hinweis, typischerweise einem Deep Link in den ersten Reiter, wo die Anmeldung die Zuordnung herstellt. Das Paket legt nie ein Konto auf Verdacht an.

### Ausgang

`BotClient` holt das App-Token beim eigenen Tenant (Client-Credentials, Scope `https://api.botframework.com/.default`) und cacht es. Er legt persönliche Konversationen an, schickt Text und Karten und ersetzt Karten nach einem Klick.

Zwei Notification-Kanäle reihen sich in Laravels `via()` ein:

- `TeamsActivityChannel` für den Aktivitätsfeed über Graph (`sendActivityNotification`). Er sorgt dafür, dass die App beim Empfänger installiert ist, weil die RSC-Berechtigung erst mit der Installation entsteht. Doppelversand sperrt er über `teams_notifications`.
- `TeamsBotChannel` für eine Karte oder Nachricht im Bot-Chat. Die Notification liefert sie über `toTeamsBot()`.

Für Tests der Anwendung gibt es `Teams::fake()`. Es fängt ausgehende Aktivitäten und Feed-Meldungen ab und bietet Assertions wie `assertCardSent()`.

### Anmeldung im Tab

`POST {präfix}/auth/sso` nimmt das Entra-Token aus dem Tab, prüft es (v2-Token, Audience gleich Client-ID oder App-ID-URI, `tid` in der Liste erlaubter Tenants), löst die Identität auf und gibt eine Sitzung für die Anwendung zurück. Die Sitzung stellt eine austauschbare Klasse aus. Standard ist ein Sanctum-Token mit der Ability `teams` und acht Stunden Laufzeit. Sanctum ist deshalb nur empfohlen, nicht vorausgesetzt.

### Identität

`ResolvesTeamsIdentity` löst geprüfte Claims auf einen User auf. Mitgeliefert ist die Strategie über die Mail-Adresse (`preferred_username` bzw. `upn` gegen die E-Mail des Users). Eine Anwendung mit eigenem Personenschlüssel bindet eine eigene Klasse.

### Karten und Links

`Cards` ist ein kleiner Baukasten für Adaptive Cards in Version 1.2: Text, Faktenliste, Knöpfe. `Action.Execute` bekommt immer einen `fallback` auf `Action.Submit`, weil nicht jeder Teams-Client den Invoke zustellt. Wer mehr braucht, übergibt das Karten-Array direkt.

`DeepLink` baut Links in einen Reiter mit optionaler `subEntityId` und nimmt nur Reiter an, die im Manifest-Abschnitt stehen.

### Befehle

| Befehl | Zweck |
|---|---|
| `teams:manifest` | Manifest aus der Config bauen und mit den Icons zippen |
| `teams:install {user}` | App per Graph beim Nutzer installieren, inklusive RSC-Consent |
| `teams:link {user} {upn}` | Identität von Hand zuordnen |
| `teams:check` | Konfiguration prüfen: Token holbar, Bot erreichbar, Manifest gültig |
| `teams:send-test {user}` | Testkarte und Test-Feed-Meldung zustellen |

## Der Nuxt-Layer

`@mirkohaaser/nuxt-teams` ist ein Nuxt-Layer ohne Abhängigkeit zu Nuxt UI. Er bringt mit:

- `useTeams()`: initialisiert `@microsoft/teams-js`, holt das SSO-Token und tauscht es am Endpunkt der API, wiederholt nach erteiltem Consent mit kurzer Pause, liest die `subPageId` aus dem Deep Link und setzt das Teams-Theme auf den Color-Mode. Fehlerdiagnose steht im Tab selbst, weil an die Entwicklertools des Desktop-Clients kaum jemand herankommt.
- das Layout `teams` ohne Navigation der Anwendung,
- die Seite `/teams/consent` für den Consent-Dialog,
- Route-Rules mit `Content-Security-Policy: frame-ancestors` für die Teams- und Microsoft-365-Hosts auf `/teams/**`.

Die Reiter selbst sind Seiten der Anwendung unter `/teams/…`. Der API-Pfad für den Token-Tausch steht in der Runtime-Config.

## Dokumentation

`docs/guide/` übernimmt die Blaupause aus der Ursprungsanwendung, auf Englisch und ohne Bezug zu Kunden: Entscheidungsbaum, Entra-Setup mit den sieben vorautorisierten Client-IDs, Manifest, Activity Feed, Tab-SSO, Bot und das Fallstrick-Journal. Das Journal bleibt der wertvollste Teil und wird bei jeder neuen Anbindung weitergeführt.

## Lizenz und Veröffentlichung

MIT. Das Paket wird in Anwendungen eingebunden, deren Code nicht offen ist, darunter eine Kundenanwendung. Unter der AGPL der Agenten-Plattform ginge das nicht.

GitHub ist führend. Packagist zieht über den GitHub-Hook. Ein Tag `vX.Y.Z` veröffentlicht per GitHub Action den Nuxt-Layer mit derselben Versionsnummer auf npm. Unterstützt werden PHP ab 8.3 und Laravel 12 und 13.

Tests laufen mit Testbench und PHPUnit. JWTs werden mit eigenen Testschlüsseln signiert, HTTP läuft über `Http::fake()`. Kein Test ruft Microsoft.

## Umstieg der Ursprungsanwendung

Die Ursprungsanwendung stellt als erste um, bevor eine zweite Anwendung dazukommt. Laufen ihre Reiter, Karten und Erinnerungen danach unverändert, ist das Paket geprüft.

Ihre Tabellen heißen schon `teams_identities`, `teams_notifications` und `teams_action_log`. Eine Überleitungs-Migration in der Anwendung legt `teams_conversations` an, überträgt die beiden Bot-Spalten aus `teams_identities` und benennt `teams_action_log` um. Die Paket-Migrationen werden dort nicht veröffentlicht. Fachliche Teile wie die Aktionen, die Übersichtsseite und die Erinnerungsjobs bleiben in der Anwendung und melden sich über die Registry an.

## Offene Punkte

- Ist der npm-Scope `@mirkohaaser` frei, und gibt es ein Packagist-Konto für den Vendor `mirkohaaser`?
- Name des GitHub-Repos. Vorschlag: `laravel-teams`.
- Kanal- und Gruppenchat-Szenarien sind im Datenmodell vorgesehen, aber erst mit dem ersten echten Bedarf zu testen.
