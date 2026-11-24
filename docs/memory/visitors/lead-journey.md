---
name: lead-journey
description: Visitor journey start file (visits tied to a contact with the marketing yes, Journey card, Interest score, Hot contacts, Lead sources, API): what binds it, file map, J1-J15 with PRs, what is not built
---

## What is it?
- Owner (2026-11-20, agent channel poll 183, message 10612; `workflow/decisions-made-product-90.md`): visits tied to a known contact with consent, the journey, a decaying interest score, interests, alerts, first and last touch, KVKK, API.
- Built in 2026.11.0 by LJ1a #10842, LJ1b #10882, LJ2 #10946, LJ5 #11116, LJ3 #11216, LJ3b #11245, LJ4 #11275, LJ4b #11291, LJ4c #11301, LJ6 #11315; closed out 2026-11-24.

## What binds it?
- A visit is tied only with the cookie notice's marketing yes; refused, unanswered or notice off: no key, no tie, the totals stay as before. The key cookie has its catalogue row and is deleted when the yes is taken back.
- A tie starts only from the person's own act (form, newsletter yes, sign-in, order, a campaign e-mail's web version by its recipient's code); never from an IP or a guess. The campaign-wide `?t=` code ties nobody.
- `visitors` writes are not slowed: the journey is its own store; scores come from the job, never the visitor's request.
- A panel user is never tied or scored (J14). Events carry contact, score and reasons, never pages.
- The person's export and erase cover the journey; the keep period (13 months, a setting) trims it.
- Architect (2026-11-20, message 10748): four tables (`lead_browsers`, `lead_visits`, `contact_journey`, `contact_scores`), no column on `visitors` or `contacts`; the owner said yes in poll 194 (`workflow/decisions-made-schema-42.md`). Topics are the page's folder, on a product page its category; no new field.
- Architect (2026-11-21, message 11218): no per-recipient click code is added; a booking makes no tie (it writes no contact, nothing is matched by e-mail).

## Which file holds what?
- Pure rules: `lead-journey-rules.md`. Tables, cookie, ties: `lead-journey-store.md`. Journey card, erase, export, trim: `lead-journey-view.md`.
- Scores, interests, Interest card: `lead-score.md`; settings, Score column: `lead-score-settings.md`. Events, alert: `lead-tell.md`.
- Hot Contacts view: `lead-hot-contacts.md`, `lead-hot-contacts-followed-by.md`. Lead sources: `lead-sources-report.md`. API: `api/api-lead-journey.md`.

## Which feature landed where?
- J1 key under the marketing yes, J2 ties, J3 move on a shared browser, J4 tie back in the keep period: #10842 (rules), #10882 (store, cookie, save paths); keep period setting #11245.
- J5 journey on the contact screen and Workspace card, J13 export, erase, inventory, trim: #10946.
- J6 behaviour points and half-life, J7 fit score, J8 the job's pass, J9 interests: #11216; settings, product category topic: #11245; group conditions by score range and topic: #11291.
- J10 events and flow start boxes: #11275. J11 Hot contacts view and daily alert: #11275, #11291; Followed by #11301.
- J12 first and last touch, Lead sources report, Excel: #11116. J14 panel users left out: #10882 on. J15 routes and the MCP read tool: #11315.

## Where does the code differ from the plan's words?
- J6: the weights are set on Site Settings › Visitors and privacy, row "Interest score" (no Contacts category exists).
- J10: named `customer.hot` / `customer.returned`, not `contact.*`: the API calls a contact a customer.
- J11: "Followed by" reads open follow-up tasks; contacts have no assignee field.
- J12: the report groups by Channel, Campaign and Landing page; no separate content page grouping.
- J15: the contact routes sit under `/customers/…` (`/customers/hot` is the MCP tool).

## What is not built?
- J5/J6 downloads: the journey reads and scores `contact_journey` kind 2 (download), but nothing writes such a row yet.
- Left out, each a later yes: company lookup by IP, outside data, guessing a person across devices, AI scoring.
- No running site: the screens and queries were checked by unit tests only.

Related: docs/memory/workflow/decisions-made-product-90.md
