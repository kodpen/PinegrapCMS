---
name: ad-conversions-steps
description: Plan ad-conversions, steps SC1 to SC7: the record and queue with the purchase event, Meta, GA4, TikTok, the other events, log and API, close-out
---

## Which steps are there?
- SC1 (M) first: the record and the queue.
  - The keeping of C1 where the architect decides (a 2026.11.0 migration step when it is a table, idempotent helpers), its sweep, and its place in the privacy export and erase.
  - New `includes/fn/ad_conversions.php`: the pure event builder (purchase, the hashing of C2, the event id), the queue write at completion and the hand-off to the queue's dispatcher. Tests red first: consent given, refused, notice off; a marketplace order; a payment service's message completing the order.
  - Features: C1, C2, C3, C10.
- SC2 (M) after SC1: Meta Conversions API.
  - The Connections page and its registry row, the sealed keys, the sender and its answers; the body read from Meta's own document into `api/meta-capi-facts.md`. Tests on recorded answers. Features: C8 (Meta), C9.
- SC3 (S) after SC2: GA4 Measurement Protocol: the API secret on its page, purchase and refund, the validation endpoint; `api/ga4-mp-facts.md`. Features: C4, C8 (GA4).
- SC4 (S) after SC2: TikTok Events API: page, sender, test code; `api/tiktok-events-facts.md`. Feature: C8 (TikTok).
- SC5 (S) after SC2: Lead, CompleteRegistration, add to cart and begin checkout per the architect's answer; the receipt page's event id. Features: C5, C6, C7.
- SC6 (S) after SC3 and SC4: the event log on each page, the daily counts, the operations alert, `GET /reports/ad-conversions` and `php tools/check_api_schema.php`. Features: C11, C12.
- SC7 (S) close-out: every C number checked against the code with its PR; `api/ad-conversions.md` written; `plans/integrations-census-today-2.md`'s Conversions API line made true; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- SC3, SC4 and SC5 side by side after SC2; each adds its own Connections page, so the registry rows are written apart.
- `connections.php` is entered by `plans/connections-merges-build.md` steps too: check the open PRs before claiming.
