---
name: cookie-consent-notice
description: Cookie notice: Settings, Reject all and Accept all drawn alike, nothing pre-ticked; Consent Mode v2 default at the top of <head>, update before held scripts start; focus
---

## What does the first view offer?
- With an optional category on the page: Settings, Reject all, Accept all, all `pg-cc-btn` (none `pg-cc-primary`), in `.pg-cc-choices` (`grid-template-columns: auto minmax(0, 1fr) minmax(0, 1fr)`, so Reject and Accept are always as wide as each other; `text-wrap: balance` breaks "Tümünü / reddet" and "Tümünü / kabul et" the same way; stacked under 300px). Without one: Settings and OK, as before. Focus on open stays on Settings (first in the DOM), so neither answer is preselected.
- Why: the KVKK cookie guide (2022, revised July 2025) and the EDPB banner report want refusing as easy as accepting, on the same layer and with equal prominence. Before PR #619 the first view had only Settings and a highlighted Accept all; Reject sat one click deeper ("Only necessary"), and only the close button refused on the first view.

## Is any category ticked for the visitor?
- No. `pg_consent_markup()` draws every optional switch off and `sync()` in `pg_consent.js` turns on only what the stored answer allows. Before PR #619 statistics and marketing came pre-ticked in Settings, so "Save choices" without touching anything accepted both (not valid consent, CJEU Planet49).

## How does Google Consent Mode v2 work?
- `pg_consent_insert()` (behind `pg_consent_inject()`) puts `pg_consent_mode_script()` straight after `<head …>` (after a `<meta charset>` that opens it; `pg_consent_mode_position()`), ahead of every script, the software's own and GA included. It queues `consent default` on `dataLayer` like Google's snippet and does not define `window.gtag`. States come from the stored answer (`pg_consent_mode_states()`): statistics → `analytics_storage`, marketing → `ad_storage`, `ad_user_data`, `ad_personalization`; unanswered or refused → denied. No `wait_for_update`: the default already is the stored answer.
- `pg_consent.js` `signal()` sends every answer, and the stored one on each page view, as `consent update` before `start()` revives held scripts (and before the reload of a revoke), through `window.gtag` when the site has one, otherwise as an Arguments object on `dataLayer`. Verified order on an accept: default denied → update granted → GA `js` → `config`.
- A page without a `<head>` tag gets no default, only the updates. The default is computed per request from the cookie, like the server-side GA hold.
- A Google tag the site adds without `data-pg-consent` (Tag Manager, Ads) now runs under denied defaults. To have the notice ask about its category, the site adds an empty held tag (`<script type="text/plain" data-pg-consent="marketing" data-pg-consent-name="Google Ads"></script>`); the Cookie Consent card says so. Before PR #619 there was no default at all and an update went out only when `window.gtag` existed.

## What changed for keyboard users?
- Settings moves focus to the first switch: the pressed button hides with its view, and focus used to fall back to the page, so Escape no longer reached the notice and Tab started over from the top.

## What is not built?
- Settings for the button texts (needs `config` columns; ask first); texts come from `lang()` and, in languages without a language file, from the "Cookie window" group of Translations.
- A consent log of answers: `privacy-data-pack-audit-log.md`.

## Where are the tests?
- `tests/consent_test.php`: first view and settings buttons, no `checked` switch, Consent Mode states, the default script, its position, insertion, and the state names in `pg_consent.js`.
