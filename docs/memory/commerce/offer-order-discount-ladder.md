---
name: offer-order-discount-ladder
description: Tiered order discount (ladder) and how the order discount is chosen: rungs, nothing below the first rung, every valid order discount weighed against the final subtotal
---

## Where is the ladder kept?
- In an `offer_conditions` row of type `tiers`, `text_value` `"<cents>:<percent>,…"` (`_pg_offer_tiers_text()`), read by `pg_offer_tiers()` highest rung first. It belongs to the offer's percentage `discount order` result (the editor's "tiered" mode), not to the offer's conditions.
- The action row keeps the first rung's percentage (`edit_offer_f.php`) only so the engine reads it as an action that discounts something; it never stands in for a rung the cart has not reached.

## Which rate does a laddered offer take?
- `pg_offer_order_discount_percentage($tiers, $subtotal, $action_percentage)`: the rung the subtotal reached, 0 below the first rung, the action's own percentage only when there is no ladder (also when `offer_conditions` is missing on a site that has not upgraded).
- `pg_offer_order_discount_value($amount, $percentage, $tiers, $subtotal)`: what one action takes off in minor units: the amount when it has one (an amount comes before a percentage), else the percentage of the subtotal, rounded; never more than the subtotal.

## How is the order discount chosen?
- `apply_offers_to_cart()` keeps every valid `discount order` action as a candidate and chooses after its loop, against the cart as it then stands: `pg_offer_best_order_discount($candidates, $subtotal)`, the most taken off, the first candidate on a tie, `array(0, 0)` when nothing is taken off (`discount_offer_id` 0, the session's `order_discount` removed).
- Chosen after the loop because a gift taken from a paid line lowers the subtotal: a ladder can fall below its first rung and another order discount can then be worth more.
- `get_best_offer_id()` (which code-entered offer is best) weighs order discounts with the same `pg_offer_order_discount_value()`.
- The cart widget's applied-offers list (`widgets_cart.php`) does not list a laddered offer below its first rung.

## What was the symptom? (#726)
- The "Kademeli sepet indirimi" template (₺100 → %5, ₺250 → %10, ₺500 → %20, no other condition) gave a ₺50 cart 5%: below the first rung every cart took the first rung's rate, because the ladder's 0 fell back to the action's percentage. A gift that took the cart below the first rung kept the 5% too, and a flat order discount next to the ladder never won below the rung.

Related: docs/memory/ui/offer-editor.md, docs/memory/commerce/offer-discounted-units.md
