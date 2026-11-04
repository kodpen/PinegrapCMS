---
name: store-credit-mails
description: Store credit mails and expiry (11.0.171): credit_issued and credit_expiring actions, fields, the notice pass without a column, the job closing ended cards
module: store_credit_mails
---

## Which mails are there?
- Campaign profile actions `credit_issued` and `credit_expiring` (#5039 question 2; step `upgrade_2026_11_0_credit_campaigns()`, 11.0.171, widens `email_campaign_profiles.action` and `email_campaigns.action`, appending only). No profile, no mail.
- They need commerce access like the order actions (form and API, `pg_email_campaign_profile_credit_actions()`) and are offered once `pg_credit_mail_ready()` (both ENUMs list them); the API answers 503 before that. Tied to no record (`action_item_id` 0), sent after the action; the campaign row keeps the card id (`campaign_item_id`).
- To the card's user (`user.user_email`, contact `user.user_contact`, so a commercial profile respects opt-out).

## When do they go?
- `credit_issued`: `pg_credit_issue()` calls `pg_credit_mail_issued()` after the audit line, for every kind (points, refund, manual). A refund to credit also sends its own refund mail; a shop with a `credit_issued` profile sends both.
- `credit_expiring`: once, `PG_CREDIT_EXPIRING_DAYS` (14, a module constant) before `expires_at`, from the general job (`pg_credit_mails_run()` in `job.php`, before the mail pass).

## How does the notice go once without a column?
- No `notified` column: like booking reminders (`booking-messages.md`), the pass keeps its last time in `config_text` `credit_mails_at`, written before sending (a pass that dies loses notices, never doubles them); a first pass looks back 15 min, a pass after a long stop at most two days (`pg_credit_mail_window_start()`).
- `pg_credit_expiring_due()` (pure): the moment (end − 14 days) fell in (last, now], the card has not ended, has a balance and was written before the moment (a card given with less than 14 days left gets no notice; its issued mail names the last day).

## What does the expiry pass do?
- Same job call, after the notices: cards with `user_id` > 0, balance > 0 and `expires_at` ≤ now (200 a pass) are closed by `pg_credit_expire_card()`: balance 0 over the one read (an order spending it meanwhile wins), note line "Store credit expired on <date> with <amount> unused.", audit `revoke` with `void_reason` `expired`, one activity line per pass.
- Only `expires_at` ends a card here; a staff-set earlier `expiration_date` already stops it at checkout and is not closed by the job.
- `pg_credit_staff_card_state()` reads a card past `expires_at` as expired whatever is left, so a closed card shows Expired, not Used (staff screen and My Account).

## Which fields?
- `pg_credit_mail_field_meanings()` (listed under the action on the form): `^^credit_amount^^`, `_left` (this card), `_balance` (all their credit), `_source`, `_reason`, `_last_day` (site date, empty for never), `_name`, `_account_url` (absolute My Account link; in `pg_mail_merge_address_hrefs()`'s list so the editor's http:// is dropped).

## Tests?
- `tests/store_credit_mails_test.php`: step twice in memory, fields, notice timing, window, profile form rows and values.
