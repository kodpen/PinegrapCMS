---
name: store-credit-model
description: Store credit as a gift card bound to a customer (2026.11.0, 11.0.159): gift_cards user_id/kind/reason/issued_by/expires_at, pg_credit_* writer and readers, pick order, points value, void, audit line
module: store_credit
---

## What is store credit in the data?
- A `gift_cards` row with `user_id` > 0 (indexed `idx_user`). `kind` ENUM gift/points/refund/manual (default gift), `reason` VARCHAR(255), `issued_by` (0 = the shop), `expires_at` unix time (0 = never). Step `upgrade_2026_11_0_store_credit()` (11.0.159); every older card stays `user_id` 0, kind gift: a plain gift card. Decided in #5039; no second table.
- `expires_at` is the moment the card stops; `pg_credit_issue()` also writes `expiration_date` (what `submit_order.php` and the gift card screens already check) as the last valid day: `pg_credit_expiration_date()`, midnight belongs to the day before. A staff edit of `expiration_date` on the gift card screen does not move `expires_at`, so the readers check both: `pg_credit_open_sql()` and `pg_credit_card_open()` need `expires_at` and `expiration_date` to allow the card, and balance, pick and checkout agree.

## Which functions are there? (`includes/fn/store_credit.php`)
- `pg_credit_ready()`: the columns exist (`pg_schema_has()`); every reader and writer asks it (upgrade bridge).
- `pg_credit_issue($user_id, $amount, $kind, $reason, $expires, $issued_by)`: amount an int in minor units, kind not `gift`, expiry in the future or 0, the user must exist; code from `generate_gift_card_code()`, never shown to the customer. Returns the card id or 0. Checks: `pg_credit_issue_check()` (pure).
- `pg_credit_cards($user_id)` open cards oldest expiry first; `pg_credit_balance($user_id)` their sum; both use `pg_credit_open_sql()` (balance > 0, not expired).
- `pg_credit_pick($cards, $amount, $now)` (pure): soonest expiry first, never-expiring last, ties by id; a card with balance 0 (spent or voided) or `expires_at <= now` is never picked; the last card gives only what is left. The checkout does not use it: it applies every open card and lets the redemption take what is needed (`store-credit-checkout.md`).
- `pg_credit_points_value($points, $points_per_unit, $smallest)` (pure): `round($points * 100 / rate)`, 0 for no rate or under the smallest; SC3 passes the settings.
- `pg_credit_reduce($card_id, $amount, $note)`: takes up to the amount off a bound card `WHERE balance = <read>`, a note line, audit `update` (`revoke` at 0); returns what it took. Used by the points take-back (`store-credit-points.md`).
- The job closes ended cards: `store-credit-mails.md`.
- `pg_credit_void($card_id, $reason)`: balance to 0 `WHERE balance = <read>` (an order spending meanwhile makes it fail), a note line "Store credit voided on <date>: <reason>"; only cards with a `user_id`.

## Who may spend a bound card by its code?
- Only its customer, signed in as themselves: `pg_credit_card_usable_by($card_user_id, pg_credit_checkout_user())` (guest and ghost orders are 0). The typed-code apply in `express_order.php` and `order_preview.php` and the refresh in `submit_order.php` read the owner (`pg_credit_owner_select_sql()`, `0 AS user_id` before the upgrade) and treat someone else's credit as no card, so staff screens and the `gift_cards:read` API that show codes cannot spend it. Symptom held in review (#6156): the code path checked code, balance and date, never the owner.

## What reaches the audit trail?
- `pg_audit('create' | 'revoke', 'store_credit', id, …)` with user_id, kind, amount, balance, reason, issued_by, expires_at (and `void_reason` on revoke); label is the code with all but the last 4 digits as `*`. The audit screen names the type "Store credit" and links `edit_gift_card.php`.

## What about personal data?
- The privacy export takes cards by `user_id` as well (code cut to its end as before). Erasure does not touch `user_id`: the row holds no personal data beyond the id; whether erasing a customer should void their credit is open (SC6 or a decision question).
