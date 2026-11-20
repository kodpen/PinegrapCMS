---
name: referral-report-and-event
description: Referrals Report (view_referral_report.php): who sees it, what each column counts from the referral rows, top referrers' names; the referral.rewarded event for webhooks and flows
module: referral_report
---

## Where is the report and who sees it?
- Visitors menu, the Order Reports group: "Referrals Report" (`view_referral_report.php`), under `validate_ecommerce_report_access()` like the Customers Report. No new permission column. The link shows whether the programme is on; with it off the screen says so and still counts past rewards.
- Top referrers' names (and the link to the contact) only for a reader who passes `validate_contacts_access($user, true)`; others see rank and figures with "Names are shown to users who may manage contacts."
- Help topic `commerce-cont#referrals-report`: the section is not written on pinegrap.com yet.

## What does it count?
- Read from the referral `commissions` rows of the last twelve months, the current one included (`pg_referral_report_rows()`, one query with the orders, one for their `order_refunds`), never from orders by code. A row is a friend's order that earned a reward; its month is the row's `created_timestamp`, the order's completion.
- Per month (`pg_referral_report_months()`): Referred orders = every row; Rewards kept = `paid`; Taken back = `ineligible`; Store credit given = the `paid` rows' amount; Referred orders' value = the `paid` rows' orders by `pg_referral_order_value()` less refunds (the larger of `order_refunds` and `refunded_amount`, as `pg_referral_refunded_total()`). A taken-back reward counts neither credit nor value.
- A restore's second card is not counted twice: the row goes back to `paid` and is one row.
- Figures and chart through `pg_report_view_*` and `pg_report_view.min.js`, as the Customers Report; the chart is value with credit dashed, none for no rewards.
- Top referrers (`pg_referral_report_top()`, ten): rows grouped by the contact the signed code names (`pg_referral_code_contact()`, a bad signature dropped), only those with a kept reward, by rewards kept, then credit, then lower contact id.

## What is referral.rewarded?
- Announced by `pg_referral_order_completed()` once per reward, after `RELEASE_LOCK` (`api-webhook-events.md`: announce after the lock). Data from `pg_referral_rewarded_event()`: `id` (the commission row), `reference_code`, `affiliate_code`, `order_id`, `amount`, `customer_id` and `account_id` (the referrer), `card_id`.
- Not announced: the take-back (cancel, refund) and the restore's giving again; those cards are `credit.issued` only.
- Flows: in `pg_event_workspace_events()`, the start box values (`flow_anchors.php`), the watch catalogue about the referrer's contact (`watch.php`, listed once `pg_referral_ready()`), the Customers area of the quick start (`flow_quick.php`). Its area on "New subscription" is Store credit (`api_app_event_area()`).

## Tests?
- `tests/referral_report_test.php`; the payload fixture in `tests/webhook_event_payloads_test.php`; the flow parity row in `tests/workspace_flows_parity_test.php`.
