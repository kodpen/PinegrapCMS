---
name: look-edit-screens-wrapped-cards
description: Add and edit screens in look A: a card takes the look card only straight in its column; how wrapped cards, forms and full-width cards are handled
---

## How does a card stay a look card when markup sits around it?
- Only a card straight in its column takes the look; one inside a `div.row > div.col-12`, a `<form>` or a collapse box stays the flat grey card. Unwrap it; let the form hold the whole row (ERP account and till) or be the card or the bar column itself (`form.card`, `form.col-12.order-3`: Edit Invoice, Edit Delivery Note); put `collapse` and the id on the card (Field Settings, System Options).
- A card that must stay outside the columns (a full-width table under them, the user strip, Store Credit) takes the recipe class `pg-card`. A status box that must not pin keeps `position-sticky` without an offset (`pg_user_form_layout()`, `#pg_fiv_status`).
- The delivery note's line editor reads its settings from `erp_waybill_form_editor_attributes()`, which Create Delivery Note puts on the form column (`'wrap' => false`).

Related: docs/memory/ui/look-edit-screens.md
