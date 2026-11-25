# Epic 04: Tab-SSO und Identität

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Anmeldung im Tab“, „Identität“
> Status: **erledigt** (2026-11-25)

## Beschreibung

Wer einen Reiter öffnet, ist ohne zweites Login angemeldet. Dabei entsteht auch die Zuordnung zwischen Entra-Nutzer und Anwendungs-User, die der Bot für jede Nachricht braucht.

## Tickets

### [E-04.01] EntraTokenValidator
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Übernommen: v2-Token, JWKS des Tenants, Audience gleich Client-ID oder App-ID-URI, `tid` in `allowed_tenants`
  - [x] Aussteller passend zum `tid` geprüft, nicht gegen eine Konstante
  - [x] Tests für jeden Ablehnungsgrund

### [E-04.02] Identitäts-Auflösung
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] Interface `ResolvesTeamsIdentity`, Bindung über `identity_resolver`
  - [x] Mitgeliefert: `MailIdentityResolver` (`preferred_username` bzw. `upn` gegen die E-Mail-Spalte, Spaltenname konfigurierbar, Vergleich ohne Groß-/Kleinschreibung)
  - [x] Kein Treffer ergibt `null`, nie ein neues Konto

### [E-04.03] SSO-Endpunkt
- **Status:** erledigt
- **Acceptance Criteria:**
  - [x] `POST {präfix}/auth/sso`, gedrosselt über einen benannten Limiter
  - [x] 401 bei ungültigem Token, 403 ohne Zuordnung, Grund nur im Log
  - [x] Interface `IssuesTabSession`, Standard `SanctumTabSession` (Ability `teams`, Laufzeit konfigurierbar, Standard 8 h)
  - [x] Ohne Sanctum und ohne eigene Bindung bricht der Endpunkt mit einer verständlichen Meldung ab
