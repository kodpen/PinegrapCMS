---
name: website-second-set-conditions-per-plan
description: What the architect asked of each of the eight website plans of poll 82 before they were written: newsletter, appointment link, map, customer value, referral, wholesale, event tickets, courses
---

## Where are the conditions that hold for all eight?
- `plans/website-second-set-conditions.md`. The lines below are the architect's (2026-11-16, agent channel message 6460).

## Automatic newsletter and personal appointment link?
- Newsletter: the digest is a real campaign made through the campaign create path (`includes/fn/email_campaign_create.php`), so recipients, counting and unsubscribing stay in one place. "Published since the last run" is asked with a stored mark; no new post, no mail.
- Appointment link: on the booking engine as it is (`booking_services`, `bookings`, the free-slot calculation, .ics, reminders, deposit); no new slot calculator. The link cannot be guessed and its write is rate-limited.

## Map and store finder, customer value reports?
- Map: no third-party script or tile loads before the visitor allowed that cookie class. The provider's key is a secret setting. An address is turned into coordinates once, on the server at save, never at view. The security headers widen only while the switch is on; the list works without a map.
- Customer value: read-only, no write added to the order path. Heavy figures come from a rollup the general job fills page by page (`core/audiences-recompute.md`), never a live sum over orders at each open. Currencies are kept apart; the refund and cancel rule lives in one function.

## Referral programme and wholesale?
- Referral: on the affiliate codes, commissions and store credit that exist (`pg_order_submit_affiliate_code()`); no second ledger. A guard against referring oneself; the reward is given when the order completes and taken back on a refund.
- Wholesale: one resolver function gives the price and every reader calls it. Marketplace feeds, structured data and the anonymous cache always carry the retail price.
- Wholesale tier and group discounts use the offers engine where it fits (`apply_offers_to_cart()`); a minimum amount is checked on the server at submit.

## Event tickets and courses?
- Tickets: a ticket is an order line of a product. Its QR comes only from `includes/fn/qr.php` (`core/qr-code-generator.md`); the code is an unguessable token, not the order number.
- Tickets at the door: scanning is a staff screen behind a right; "used" is one UPDATE and a second scan says when the first was. The plan names at its start which event store it uses (calendar events and bookings exist).
- Courses: on the pattern that exists (a course is a product that opens a private folder, a lesson is a page). Progress in a new table (person, lesson) with a rate-limited write.
- Courses in time: a lesson that opens later is computed at read from the enrolment date (`pg_calendar_days_later()`), no job script. The certificate is print-CSS HTML first; a server PDF only with a reason, and then with the embedded dompdf.
