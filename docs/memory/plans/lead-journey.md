---
name: lead-journey
description: Plan (owner 2026-11-20, poll 183): visits tied to a known person with consent, the journey, a decaying interest score and interests, hot and returning alerts, lead sources; LJ1a to LJ7
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
- A visit is tied only with a yes to the cookie notice's marketing category; refused, unanswered or notice off: nothing is tied, the totals stay as today.
- A new cookie gets its catalogue row (`platform/cookie-consent-new-cookies.md`); no cookie before the yes.
- Ties start from the person's own act: a form sent, a newsletter yes, a sign-in, a click through a campaign e-mail's tracking code. Never from an IP or a guess.
- `visitors` writes and its monthly split are not slowed: the journey is its own store, written after the response where possible.
- The person's export and erase cover the journey; staff are never scored.
- Events carry the contact id, score and reason, never the pages seen.

## What did the architect decide? (2026-11-20, agent channel message 10748)
- Schema: four new tables, `lead_browsers` (a consenting browser and its contact), `lead_visits` (that browser's visits), `contact_journey` and `contact_scores`; no column on `visitors` (partitioned) or `contacts`. Asked of the owner in poll 194 (message 10747); LJ1b waits for the yes, and the architect writes the columns into the schema decisions list.
- Consent: the marketing category (poll 194 asks it so); the key is written only with the yes and deleted when it is taken back (J1).
- Topics: the page's folder, on a product page the product's category; no new field (J9).
- E-mail clicks: only the existing tracking code; no new signed link (J2).
- The keep period, 13 months as a setting, and tying back only visits after the consent: poll 194 (J4).

## What is built?
- Features: `lead-journey-features.md` (J1 to J15). Steps: `lead-journey-steps.md`. Claim as `[plan:lead-journey] <step> — <topic>`.

## What is left out?
- Company lookup by IP, buying outside data, guessing a person across devices, scoring by AI: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the journey and the lead sources report are seen on dev after LJ3 and LJ5.
