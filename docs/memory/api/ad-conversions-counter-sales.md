---
name: ad-conversions-counter-sales
description: The choices block of the Meta, GA4 and TikTok pages (event switches, Lead forms, counter sales) and "Send counter sales": a local order's purchase without any personal data
module: ad_conversions
---

## What does each service page offer?
- Built in #10960. Before it only Meta's page saved the visitor events and `local` orders were never sent.
- One block, `pg_ad_conversion_choices_fields()` in `includes/fn/ad_conversions.php`, drawn by all three pages; one save rule, `pg_ad_conversion_choices_merge()` (events kept only among those the service offers, page order of `pg_ad_conversion_all_events()`).
- "Events sent": a switch row per event, label from `pg_ad_conversion_log_event_label()`, the service's own name under it. GA4 offers all six of poll 166; Meta and TikTok all but Refund (no such event, C4).
- Purchase is on for a row never saved (`events` missing → `purchase`); the others are off until switched on. Add to cart, begin checkout, sign-up and Lead go only with consent (`ad-conversions-visitor-events.md`); Lead only for the forms ticked under "Forms sent as Lead".
- "Without consent": "Send events without the person", the purchase only; on Meta's page shown off and disabled with its line (`ad-conversions-meta.md`). "Counter sales": "Send counter sales"; on Meta's page shown off and disabled, with the line "Meta takes an event only with some customer information, so counter sales are not sent to Meta." Then "Send personal data while the cookie notice is off" and, notice off and box clear, its warning.

## How is a counter sale sent?
- Owner (2026-11-20, poll 166, C3): counter sales only with a setting, never with personal data. The switch is `counter_sales` in the service's `config_text` row `ad_conversions_<service>`; no schema. Missing → off, so a site that updates sends nothing new.
- `pg_local_order_complete()` (`includes/fn/local_order.php`) calls `pg_ad_conversion_counter_sale_completed()` after its claim and `order.created`. For each service connected, Purchase on, the switch on and taking counter sales (`pg_ad_conversion_counter_sale_plan()`), a due row (status 1): event `purchase`, event id the order's reference, `ids` NULL, `person` 0, `detail` with `counter_sale: true` (`pg_ad_conversion_counter_sale_rows()`). No cookie, click id, e-mail or phone is read.
- `pg_ad_conversion_event()` never adds the person to an event whose detail says `counter_sale`, whatever the row holds, and hands `counter_sale` to the sender.
- Meta: never (`pg_ad_conversion_counter_sale_offered()`). Its documents require at least one customer information parameter in `user_data`, and a counter sale has none (`meta-capi-facts.md`); `pg_ad_conversion_meta_settings_merge()` always saves `counter_sales` false.
- Architect (2026-11-21): no personal data is added to make Meta take a counter sale; Meta gets none. GA4: a client id made from the order, consent `DENIED`, as any purchase without the person. TikTok: a web event with an empty `user` (an offline event needs an offline event set, which the page does not ask).
- The site's checkout still never sends a `local` order (`pg_ad_conversion_order_sendable()`), and a counter sale never sends add to cart or begin checkout.

Related: docs/memory/api/ad-conversions.md, docs/memory/api/ad-conversions-queue.md
