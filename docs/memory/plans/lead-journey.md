---
name: lead-journey
description: Plan (owner 2026-11-20, poll 183): visits tied to a known person with consent, the journey, a decaying interest score and interests, hot and returning alerts, lead sources; LJ1 to LJ7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 183, message 10612): "Evet, plana girsin": visits tied to a contact, the journey, interest score and interests, alerts and events, first and last touch, KVKK, API. Chosen for sites beyond the shop (corporate, B2B, agencies, education, estate, clinics).

## What is there today?
- `init_tracking()` (`includes/fn/seo.php`) writes one `visitors` row a visit; the id lives in the session, no lasting browser id. The table is split by month and its IP blanked after 30 days (`visitors/visitor-monthly-partitions.md`, `visitors/visitor-ip-early-removal.md`).
- Totals only: channel (`visitors/visitor-channel-rule.md`), search terms, conversions and the funnel (`visitors/visitors-conversions.md`), page clicks.
- Contacts come from forms, the newsletter (`core/form-double-opt-in.md`), accounts and orders; `forms.contact_id`; the Workspace customer card and history (`workspace/workspace-customer-history.md`).
- Cookie notice categories and `pg_consent_setcookie()` (`platform/cookie-consent-catalogue.md`); a person is erased through `pg_privacy_erase_tables()` (`core/contact-holders.md`).
- No visit is tied to a person; no score; nothing says which page or campaign brought a lead.

## What binds it?
- A visit is tied only with a yes to the cookie notice's category the architect names; refused, unanswered or notice off: nothing is tied, the totals stay as today.
- A new cookie gets its catalogue row (`platform/cookie-consent-new-cookies.md`); no cookie before the yes.
- Ties start from the person's own act: a form sent, a newsletter yes, a sign-in, a signed e-mail link. Never from an IP or a guess.
- `visitors` writes and its monthly split are not slowed: the journey is its own store, written after the response where possible.
- The person's export and erase cover the journey; staff are never scored.
- Events carry the contact id, score and reason, never the pages seen.

## What does the architect decide?
- Schema: a browser key on `visitors` (a partitioned table) or a side table (visit, browser key); a `contact_journey` store (contact, kind, ref, page, time); the score as `contacts` columns or a `contact_scores` table?
- Consent: statistics or marketing for the tie (profiling reads as marketing under KVKK)?
- Topics: from the page's folder, a new page or record topic field, or collection fields?
- E-mail clicks: campaign links signed with the contact (new), or only the existing tracking code?
- How long the journey is kept (default 13 months?) and whether earlier anonymous visits of the same browser are tied back.

## What is built?
- Features: `lead-journey-features.md` (J1 to J15). Steps: `lead-journey-steps.md`. Claim as `[plan:lead-journey] <step> — <topic>`.

## What is left out?
- Company lookup by IP, buying outside data, guessing a person across devices, scoring by AI: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the journey and the lead sources report are seen on dev after LJ3 and LJ5.
