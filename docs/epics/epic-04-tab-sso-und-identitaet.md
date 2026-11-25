# Epic 04: Tab-SSO und Identität

> Abgeleitet aus: [00 Überblick](../concept/00-ueberblick.md), Abschnitte „Anmeldung im Tab“, „Identität“
> Status: **offen**

## Beschreibung

Wer einen Reiter öffnet, ist ohne zweites Login angemeldet. Dabei entsteht auch die Zuordnung zwischen Entra-Nutzer und Anwendungs-User, die der Bot für jede Nachricht braucht.

## Tickets

### [E-04.01] EntraTokenValidator
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Übernommen: v2-Token, JWKS des Tenants, Audience gleich Client-ID oder App-ID-URI, `tid` in `allowed_tenants`
  - [ ] Aussteller passend zum `tid` geprüft, nicht gegen eine Konstante
  - [ ] Tests für jeden Ablehnungsgrund

### [E-04.02] Identitäts-Auflösung
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] Interface `ResolvesTeamsIdentity`, Bindung über `identity_resolver`
  - [ ] Mitgeliefert: `MailIdentityResolver` (`preferred_username` bzw. `upn` gegen die E-Mail-Spalte, Spaltenname konfigurierbar, Vergleich ohne Groß-/Kleinschreibung)
  - [ ] Kein Treffer ergibt `null`, nie ein neues Konto

### [E-04.03] SSO-Endpunkt
- **Status:** offen
- **Acceptance Criteria:**
  - [ ] `POST {präfix}/auth/sso`, gedrosselt über einen benannten Limiter
  - [ ] 401 bei ungültigem Token, 403 ohne Zuordnung, Grund nur im Log
  - [ ] Interface `IssuesTabSession`, Standard `SanctumTabSession` (Ability `teams`, Laufzeit konfigurierbar, Standard 8 h)
  - [ ] Ohne Sanctum und ohne eigene Bindung bricht der Endpunkt mit einer verständlichen Meldung ab
