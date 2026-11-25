# Epic 09: Umstieg der Ursprungsanwendung

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Umstieg der Ursprungsanwendung“
> Status: **offen**

## Beschreibung

Die Anwendung, aus der der Code stammt, stellt auf das Paket um. Ihre Reiter, Karten und Erinnerungen laufen danach wie vorher. Das ist der Nachweis, dass das Herauslösen nichts verloren hat. Die eigentliche Umsetzung steht als Epic im Repo der Anwendung, hier wird nur der Stand gespiegelt.

## Tickets

### [E-09.01] Paket einbinden
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Paket per VCS-Repository bzw. Pfad eingebunden, Nuxt-Layer per `extends`
  - [ ] Eigene Klassen unter `Services/Teams` und die beiden Controller entfernt, soweit das Paket sie ersetzt

### [E-09.02] Überleitungs-Migration
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `teams_conversations` angelegt und aus den Bot-Spalten der Identitäten befüllt
  - [ ] `teams_action_log` in `teams_actions` umbenannt, Spalten angeglichen
  - [ ] Gegen eine Kopie der Produktionsdatenbank geprobt, Rückweg geschrieben

### [E-09.03] Fachliches über die Registry
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Bisherige Kartenaktionen als `Teams::action()` angemeldet
  - [ ] Erinnerungen nutzen die Kanäle des Pakets
  - [ ] Bestehende Tests der Anwendung grün, Tests nutzen `Teams::fake()`

### [E-09.04] Abnahme in Teams
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Alle Reiter laden im Desktop-Client, im Web und in Outlook
  - [ ] Eine Kartenaktion ersetzt die Karte
  - [ ] Eine Erinnerung erscheint im Aktivitätsfeed und führt in den richtigen Reiter
  - [ ] Eine Textnachricht an den Bot bekommt die Hilfe-Antwort
