---
name: lead-journey-features
description: Plan lead-journey, features J1 to J15: the browser key and the tie, the journey, the interest and fit scores, interests, hot and returning alerts, lead sources, KVKK, API
---

## How is a visit tied to a person?
- (J1) With the consent yes only, a random browser key in a first-party cookie (catalogue row, its category, 13 months); no key without the yes, and a later refusal deletes the cookie and stops new ties.
- (J2) A tie is made by the person's act: a form that writes a contact, a newsletter yes (after the double opt-in when it is on), a sign-in, an order, a booking, a signed campaign link. The browser key then points at that contact.
- (J3) One browser, one contact: a later act by another contact on the same browser moves the key (a shared computer), the earlier ties stay with their contact.
- (J4) Earlier visits of the same browser key are tied back, inside the keep period.

## What is the journey?
- (J5) Per contact, in time order: first touch (channel, campaign, referrer, landing page), visits with their pages, downloads, forms, newsletter, campaign clicks, bookings, orders, course steps. Read on the Workspace customer card, the contact screen and the API.

## How is interest scored?
- (J6) Behaviour points from a code model (pricing or contact page, a return within 7 days, a form, a booking, a campaign click, a download), each fading by a half-life (default 30 days); the shop sets weights and pages in Site Settings.
- (J7) A fit score from fields: a company e-mail domain (not a free mail list in code), filled company and title fields, a contact group. Both scores 0 to 100, with the top three reasons kept.
- (J8) Scores are worked out by the general job for contacts touched since the last pass, never in the visitor's request.

## What are interests?
- (J9) Topics of the pages and records seen (the architect's source), weighted by time spent and fading like J6; the top five on the contact. Contact groups and flows can filter by a topic and by a score range.

## Who is told?
- (J10) `contact.hot` once when the score passes the site's threshold (default 60), again only after it fell below and rose; `contact.returned` when a hot contact starts a new visit, at most once a day per contact. Through `pg_announce()`, the catalogue, fixtures and flow start boxes.
- (J11) A daily "Hot contacts" list (score, reasons, last visit, assignee) on the contacts screen; staff told through `pg_ops_alert()` once a day.

## Which page or campaign brought a lead?
- (J12) For each contact that became a lead (a form or booking) and each that became a customer (a first order or a paid invoice): first and last touch. A report by channel, campaign and landing or content page: leads, customers, the revenue of commerce sites, over a period; Excel.

## How does KVKK hold?
- (J13) The journey, ties and scores are in the person's export and erased with the person; the keep period trims older journey rows; the privacy notice text names the profiling (`plans/privacy-centre.md` inventory catalogue gets the store).
- (J14) No tie, score or event for users with a panel role.

## Which API?
- (J15) `GET /contacts/{id}/journey`, `GET /contacts/{id}/score`, `GET /reports/lead-sources`; the score and topics on the contact read; an MCP read tool for hot contacts.
