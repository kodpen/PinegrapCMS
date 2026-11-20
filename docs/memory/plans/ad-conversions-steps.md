---
name: ad-conversions-steps
description: Plan ad-conversions, steps SC1 to SC7: the record and queue with the purchase event, Meta, GA4, TikTok, the other events, log and API, close-out
---

## Which steps are there?
- SC1 (M) first: the `ad_conversion_events` table, the consent reader and the purchase event.
  - Migration step in 2026.11.0: the `ad_conversion_events` table (`workflow/decisions-made-schema-35.md`), idempotent helpers; the columns in the PR body, asked of the architect before ready. The sweep and the privacy export and erase.
  - New `includes/fn/ad_conversions.php`: the pure consent reader, the pure event builder (purchase, the hashing of C2, the event id), the status 0 rows at the payment step, the queueing at completion, the general job's pass.
  - Tests red first: the consent reader's three inputs (notice on, stored answer, the service's box): notice off and box off, notice off and box on, notice on and no answer, yes, no; consent taken back; a marketplace order; a payment service's message completing the order; a second completion.
  - Features: C1, C2, C3, C10, C10b. `submit_order.php`: not while a `plans/stock-holds.md` SH3 PR is open.
- SC1b (S) after SC1, beside SC2: the click ids of the landing address (`fbclid`, `gclid`, `ttclid`).
  - New `includes/fn/ad_click_ids.php`: the pure list of the parameters read per service, a value's bound (length and characters; another value is dropped), the click's time. The capture where a visitor page starts: the session only, never the table or a cookie before consent; with consent into the purchase row's `ids`, dropped when consent is refused or taken back.
  - One call in the ids reader of `ad_conversions.php`; SC2 never edits that reader. Tests red first; the PR says what a cached page does.
- SC2 (M) after SC1: Meta Conversions API.
  - The Connections page and its registry row, the sealed keys, the sender and its answers; the body read from Meta's own document into `api/meta-capi-facts.md`. Tests on recorded answers. Features: C8 (Meta, with the notice-off box), C9.
- SC3 (S) after SC2: GA4 Measurement Protocol: the API secret on its page, purchase and refund, the validation endpoint; `api/ga4-mp-facts.md`. Features: C4, C8 (GA4).
- SC4 (S) after SC2: TikTok Events API: page, sender, test code; `api/tiktok-events-facts.md`. Feature: C8 (TikTok).
- SC5 (S) after SC2: Lead, CompleteRegistration, add to cart from `cart_action.php` and begin checkout (C6); the receipt page's event id. Features: C5, C6, C7.
- SC6 (S) after SC3 and SC4: the event log on each page, the daily counts, the operations alert, `GET /reports/ad-conversions` and `php tools/check_api_schema.php`. Features: C11, C12.
- SC7 (S) close-out: every C number checked against the code with its PR; `api/ad-conversions.md` written; `plans/integrations-census-today-2.md`'s Conversions API line made true; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- SC3, SC4 and SC5 side by side after SC2; each adds its own Connections page, so the registry rows are written apart.
- `connections.php` is entered by `plans/connections-merges-build.md` steps too: check the open PRs before claiming.
