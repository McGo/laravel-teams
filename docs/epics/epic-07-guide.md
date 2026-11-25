# Epic 07: Guide

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitt „Dokumentation“
> Status: **offen**

## Beschreibung

Die Blaupause der Ursprungsanwendung wird zu einem öffentlichen englischen Guide. Wer das Paket einbindet, kommt damit von der leeren Entra-Registrierung bis zur ersten Karte im Bot-Chat. Das Repo ist öffentlich, deshalb wird jede übernommene Seite auf Kundenbezüge geprüft.

## Tickets

### [E-07.01] Blaupause übertragen
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Seiten `01-decisions` bis `06-bot` übersetzt und auf das Paket bezogen (Config-Schlüssel und Befehle statt Handarbeit)
  - [ ] Keine Kunden-, Projekt- oder Produktnamen, keine echten IDs außer den sieben Host-Client-IDs von Microsoft, Beispiele mit `contoso.com`
  - [ ] ⚠-Stellen vom Stand Juli 2026 gegen die aktuelle Microsoft-Dokumentation geprüft und datiert

### [E-07.02] Fallstrick-Journal
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `90-pitfalls.md` mit allen Einträgen aus der Ursprungsanwendung, anonymisiert
  - [ ] Format beibehalten: Symptom, Ursache, Lösung, Kosten, Bereich
  - [ ] Abschnitt „Business Basic“: was in einem kleinen Tenant anders ist (Upload eigener Apps, Admin-Rollen, Propagationszeiten)

### [E-07.03] Schnellstart
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] README mit Installation, Minimal-Config, einem Befehl, einer Aktion und einem Reiter
  - [ ] Checkliste für den Admin des Ziel-Tenants als eigene Seite, damit sie sich weiterreichen lässt
