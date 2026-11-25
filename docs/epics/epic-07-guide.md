# Epic 07: Guide

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Dokumentation“
> Status: **erledigt bis auf die Nachprüfung der ⚠-Stellen** (2026-11-25)

## Beschreibung

Die Blaupause der Ursprungsanwendung wird zu einem öffentlichen englischen Guide. Wer das Paket einbindet, kommt damit von der leeren Entra-Registrierung bis zur ersten Karte im Bot-Chat. Das Repo ist öffentlich, deshalb wird jede übernommene Seite auf Kundenbezüge geprüft.

## Tickets

### [E-07.01] Blaupause übertragen
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Seiten `01-decisions` bis `06-bot` übersetzt und auf das Paket bezogen (Config-Schlüssel und Befehle statt Handarbeit)
  - [x] Keine Kunden-, Projekt- oder Produktnamen, keine echten IDs außer den sieben Host-Client-IDs von Microsoft, Beispiele mit `contoso.com`
  - [ ] ⚠-Stellen vom Stand Juli 2026 gegen die aktuelle Microsoft-Dokumentation geprüft und datiert. Teilweise: geprüft sind das Ende der Multi-Tenant-Bots, die Abschaltung der Connectors und die entfallene Graph-Abrechnung. Host-Client-IDs, CSP-Liste, Schema 1.29 und Admin-Center-Menüs sind ungeprüft übernommen

### [E-07.02] Fallstrick-Journal
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `90-pitfalls.md` mit allen Einträgen aus der Ursprungsanwendung, anonymisiert
  - [x] Format beibehalten: Symptom, Ursache, Lösung, Kosten, Bereich
  - [x] Abschnitt „Business Basic“: was in einem kleinen Tenant anders ist (Upload eigener Apps, Admin-Rollen, Propagationszeiten). Nichts davon in einem Business-Basic-Tenant getestet, der Abschnitt sagt das

### [E-07.03] Schnellstart
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] README mit Installation, Minimal-Config, einem Befehl, einer Aktion und einem Reiter
  - [x] Checkliste für den Admin des Ziel-Tenants als eigene Seite, damit sie sich weiterreichen lässt
