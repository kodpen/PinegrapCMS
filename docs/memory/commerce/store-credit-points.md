---
name: store-credit-points
description: Reward points to store credit (2026.11.0): two config_text settings, the My Account form, convert_reward_points.php, the guarded UPDATE then pg_credit_issue() kind points, the goal left as it was
module: store_credit_points
---

## Where are the settings?
- Settings › Ecommerce › reward program card: "Points per unit of store credit" and "Smallest conversion" (an amount). Stored as `config_text` rows, not `config` columns (the row's room, `data/config-table-no-varchar.md`): `credit_points_per_unit` as a plain number with a dot (`pg_credit_points_parse_rate()`, read like `pg_parse_amount()`, at most 4 decimals, capped at 1,000,000; '' = off) and `credit_points_smallest` in minor units (`pg_credit_points_parse_smallest()`, `round()`). No rate, or the reward program off, means nothing changes on the site.

## How does a customer convert?
- My Account's store credit section (built-in templates and the designer my_account widget, `store-credit-screens.md`): a number box (all their points by default, at least `pg_credit_points_minimum()`), "Convert to store credit" and what all their points are worth. Drawn only when the points reach the minimum (`pg_credit_account_convert()`).
- The form posts to `convert_reward_points.php` (POST, token, `send_to` through `pg_safe_redirect_path()`); the customer is `pg_credit_checkout_user()`, so a guest and a ghost session are refused. The answer is a notice or an error on the `my_account` liveform.

## What does a conversion write?
- `pg_credit_points_convert($user_id, $points)`: `pg_credit_points_check()` (pure: whole number above 0, within the balance, worth at least the smallest and one minor unit), then `pg_credit_points_take()`: the value is the whole minor units the points are fully worth (floor, not `round()`) and only the points that value needs are spent (`ceil(value * rate / 100)`), the rest stay. Rounding half up per conversion let a customer split points into pieces that each round up (rate 200: one point at a time gave twice the worth; review of #6256). Then one `UPDATE user SET user_reward_points = user_reward_points - N WHERE … AND user_reward_points >= N`; no row changed is `busy` (two requests at once never spend the same points). Then `pg_credit_issue()` kind `points`, never expiring, reason "N reward points converted", issued by 0; when no card is written the points are added back (`issue`).
- `pg_credit_points_value()` of the store credit model (`store-credit-model.md`) rounds half up and is not used for a conversion.

## What about the reward goal?
- Untouched: `pg_order_complete_rewards()` still fires on the order whose points cross `ECOMMERCE_REWARD_PROGRAM_POINTS`. A customer who converts and then crosses the goal again gets its membership or mail again.
- `pg_order_complete_rewards()` now adds the order's points in place (`user_reward_points + ?`): it used to write the balance it had read, so a conversion at the same moment could be overwritten and the points spent twice.
- The user screen (`edit_user.php`) posts the balance it was opened with (`reward_points_original`); `pg_credit_points_staff_sql()` writes the field only when staff changed it, so saving a record opened before a conversion or an order no longer puts the moved points back.
- The external API's `POST /users/{id}` does the same: `api_user_fields_from_row()` sets `reward_points_original` to the balance it read, so an update that does not send `reward_points` (or sends the stored figure) leaves the column alone; before, every update wrote the read balance back and an order or conversion landing in between was lost.

## Are an order's points taken back when it is cancelled or refunded?
- Yes: `store-credit-points-take-back.md`.
