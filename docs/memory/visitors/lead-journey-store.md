---
name: lead-journey-store
description: The visitor journey's four tables (step 11.0.265), the pg_lead browser key cookie under the marketing yes, the visit rows, the ties at each act's save path, a refusal, panel users (LJ1b)
module: lead_journey_store
---

## Which tables hold the journey?
- Step 11.0.265 (`upgrade_2026_11_0_lead_journey()`), owner poll 194, columns in `workflow/decisions-made-schema-42.md`: `lead_browsers`, `lead_visits`, `contact_journey`, `contact_scores`. No column on `visitors` or `contacts`; no IP, user agent or form content. All four are in `get_tables()`.
- Until the step runs `pg_lead_ready()` is false and nothing is written or listed.

## When is the browser key written?
- `pg_lead` cookie (`PG_LEAD_COOKIE`): 32 random bytes as hex, 13 calendar months (`pg_lead_cookie_expires()`), written through `pg_consent_setcookie('marketing', ...)` only when the notice is on and marketing was answered yes (`pg_lead_consent_state()`): notice off, unanswered or refused writes nothing.
- Only its sha256 is stored (`lead_browsers.key_hash`). A key whose row is gone starts a new row from the current yes.
- Catalogue row under marketing while visitor tracking is on and the tables exist; in `pg_consent_revoke_names()` too.

## How is a visit recorded?
- The key is synced once per request at the end of `init_tracking()` (`pg_lead_request_browser()`), before the page prints: `update_visitor_page_data()` runs in `get_page.php` after output has started, so a cookie written there was never sent (found on the sandbox before #10882 was ready).
- `update_visitor_page_data()` calls `pg_lead_page_view()`: so only with `VISITOR_TRACKING` and a `visitors` row. The first page of a visit inserts a `lead_visits` row (channel by `pg_visitor_channel()`, campaign = UTM campaign else tracking code, referrer host, landing page); later pages update `pages` JSON `[page, seconds, at]`, at most 50 (`pg_lead_pages_add()`). A tied browser's new visits carry its contact at once.

## Which acts tie a browser? (`pg_lead_tie($act, $contact_id, $ref_id)`)
- `sign_in`: `pg_session_sign_in()` (`includes/authentication.php`) via `pg_lead_sign_in()`, the account's `user_contact`; skipped when the request was already signed in as another user (`login_as_user.php`).
- `newsletter`: the double opt-in confirm (`confirm_subscription.php`) and a new group yes on `email_preferences.php`.
- `campaign_click`: `view_email_campaign.php?r=<reference_code>` when the `email_recipients` row has a contact (`ref_id` = campaign). The `?t=` code ties nobody.
- `order`: `submit_order.php` after `pg_order_complete()` returned a number, with the order's contact (`ref_id` = order).
- On `tie`/`move`: `lead_browsers.contact_id`, `tied_at`; untied visits since the consent and inside the keep period get the contact (`pg_lead_tie_back_ids()`); one `contact_journey` row, kind 1 (tie), `detail` = act, `ref_id` = group or user.
- `form`: `custom_form.php` right after `UPDATE forms SET contact_id`, so a form's group yes without double opt-in ties with it (`ref_id` = form).
- No booking tie (a booking holds no contact).

## What does a refusal do?
- `consent_answer.php` → `pg_lead_consent_changed()`: on a marketing refusal the browser row goes with its visits where `contact_id = 0`, the session part is dropped and the cookie deleted. A page view with the cookie but no yes does the same (`pg_lead_sync_plan()`); if the script already deleted the cookie the session names the row.

## Who never gets a row?
- A panel user (`pg_user_has_panel_access()`) or staff browsing as a customer: no key read or written, no visit, no tie.

## What do merge and erase do?
- `pg_contact_holders()`: the three row tables `move`; `contact_scores` `drop` (a new merge answer: merged contacts' rows deleted, the survivor is scored again). Erase is `none` until LJ2 adds the export and erase hooks.
