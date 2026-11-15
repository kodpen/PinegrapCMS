---
name: cart-recovery
description: Plan (owner 2026-11-15, agent channel message 5096 and poll 50): recovered carts: the record of touches, the figures by channel and message, what weakens recovery; steps CR1 to CR4
priority: 6
---

## What is asked?
- Owner (2026-11-15, agent channel message 5096): "bir kampanya eposta veya whatsapp dan, yada bir sayfadaki teklif sayesinde kurtarılan sepet sayısını gösterecek bir alanımız olsun istiyorum ... verileri elde tutalım ve geri dönüşleri neyin zayıflattığını sistem bulmaya çalışsın."
- What exists today is in the R&D agent's proposal (agent channel message 5112): only the `order_abandoned` mail, nothing records that a sent one worked, `?r=<reference code>` restores a cart and writes nothing.
- His answers (poll 50): a new table, and a cart counts as recovered when completed within 7 days of the last touch: `workflow/decisions-made-product-56.md`, `workflow/decisions-made-schema-21.md`.

## Which steps are there?
- CR1 (M) The record. Given 2026-11-15 (agent channel message 5144), `@elcin`.
  - A migration step adds one small table of touches: one row per message sent or offer shown to a left cart (the order, the channel, what sent it, the sent, clicked, restored and completed times, the order's total at completion). Table `cart_recovery_touch`: its eleven columns and four indexes are in the body of #9778, confirmed by the architect on 2026-11-15 (agent channel message 5161 and its answer).
  - Written where the abandoned-order mail is sent (`job.php`), where `?r=` restores the cart (`_eo_restore_cart_from_reference_code()`) and in `pg_order_complete()`.
  - One pure function with tests: an order is recovered when its last touch is at most 7 days before the completion; that touch takes it. A left cart completed with no touch is counted apart. "Left" stays today's 6 hours.
- CR2 (M) after CR1: the figures. A Recovered carts section (left, reached, returned, recovered, revenue; by channel and by message), a dashboard card, `GET /reports/cart-recovery`. A new screen: its pictures go to the owner, who also says where the section sits.
- CR3 (S to M) after CR2: what weakens recovery. Pure rules over the figures with minimums, as `alerts.php`: carts no message can reach (no address or no consent), a message sent much and clicked little, clicked but not completed and at which checkout step, a late send, lines out of stock.
  - "Clicked but not completed" reads the order's own status as well as the touch: a cart completed more than 7 days after its last touch is neither recovered nor untouched (no `completed_total`, no `channel = ''` row); CR2 counts it among left, reached and returned, never recovered.
- CR4 (S) after CR1, done (#9799): WhatsApp's single-use offer keys (`whatsapp-offer-codes.md`) and an offer shown on a page (the pop-up plan's take-up) as touches: `commerce/cart-recovery-whatsapp-page.md`.
- Last (S): close-out: the topic files in `commerce/`, this plan deleted.

## What is left out?
- A flow start "When a cart is left" (recovery by WhatsApp or SMS through a flow): a later plan (`core/campaign-profile-flow-gaps.md`).
- The touch table is small (a row per message, not per visit), so no rollup at first; the figures read it by date range.

## Whose look?
- CR2's look is given: Owner (2026-11-15, agent channel poll 55): "Evet, böyle çıksın", the section on the Visitors overview between Conversions and Sources (`workflow/decisions-made-product-57.md`).
