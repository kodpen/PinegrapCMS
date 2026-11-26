---
name: bug-hunt-areas-lead-journey-store
description: Bug hunt of the visitor journey's store, ties and first touch (LJ1a, LJ1b, LJ5): what was read, the eight faults fixed (#11806), what was found clean, what was left for the owner
module: lead_journey_store
---

## What was the area, and what was read?
- Architect, agent channel: the visitor journey's store, the ties at their save paths, the lead and customer marks and the Lead sources report (LJ1a, LJ1b, LJ5). The score, Hot Contacts, erase and export: `bug-hunt-areas-lead-scoring.md`.
- Read: `includes/fn/lead_journey.php` (key, tie, keep, sync plan, pages), `lead_journey_store.php`, `lead_touch.php`, `lead_sources.php`, the tie calls in `custom_form.php`, `submit_order.php`, `view_email_campaign.php`, `confirm_subscription.php`, `email_preferences.php`, `includes/authentication.php`, `login_as_user.php`, `init_tracking()` / `update_visitor_page_data()` in `seo.php`, the booking and invoice callers, and the tests `lead_journey`, `lead_touch`.

## What was fixed? (#11806)
- After "Log in as this user" the staff member was not taken for staff: the session flag checked (`ghost`) is written nowhere, so the customer's browser key, visits and an order's tie came from staff browsing. Now `pg_lead_session_is_staff()` also reads `logged_in_as_different_user`.
- A campaign e-mail's web version opened by a recipient whose contact was deleted (Delete leaves `email_recipients.contact_id`) tied or moved the browser to the missing contact. Now `pg_lead_tie_decision()` answers `no_contact` for `contact_gone`.
- Two acts of one contact at once (a form sent twice, an order and a paid invoice) both found no lead or customer row and wrote two. Now `pg_lead_became()` checks and writes under `GET_LOCK` (`pg_lead_became_lock_name()`), `busy` after 5 seconds.
- A cancelled booking counted as an earlier lead act, so the contact's real first form or booking was `not_first`; an abandoned deposit checkout ends as a cancelled hold carrying the customer. Now only `pg_lead_touch_booking_statuses()` (requested, confirmed, no_show, done) count, as a cancelled order counts for nothing.
- Lead sources by Campaign showed a touch whose visit was trimmed as "No campaign". Now such a touch is its own group (`gone`), labelled "Not known" on the screen, in the Excel and in the API.
- A merged contact holding two lead or two customer rows was counted twice (leads, customers, revenue). Now `pg_lead_sources_read()` counts each contact's earliest row of a kind.
- "Became customers" counted a customer row from before the lead; now only one at or after it.
- On a move the open visit stayed with the earlier contact and the new contact's tie row named it; now the visit is split.

## What was found clean?
- The key only with the marketing yes, a refusal deleting the row with its untied visits (tied visits stay with the contact), a key whose row is gone starting again from the yes, `INSERT IGNORE` on the key hash, the notice switched off deleting the key.
- The first page against later pages, the 50-page cap keeping the landing page, a visit as the PHP session (midnight changes nothing), tracking off writing nothing, a panel user getting no row.
- Tie back only since the consent and inside the keep period, never moving a tied visit; sign-in as another user making no tie; the touch pick at the same second (higher id later); month edges in the site's time zone; revenue as minor units over 100; revenue hidden without the order reports right, on the screen and in the Excel.

## What did the architect answer?
- Architect (2026-11-26, agent channel request 3081): "Became customers" counts only a customer row at or after the lead; fixed here. On a move the open visit is split (`pg_lead_move_split()`); fixed here.
- Follow-up to #11806, built: a cancelled or fully refunded first order is no customer and brings no revenue, judged at read; the next counting order takes the row over (`visitors/lead-sources-customer-rule.md`).

## Which issues were opened?
- None.
