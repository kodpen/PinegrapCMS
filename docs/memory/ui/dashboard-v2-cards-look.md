---
name: dashboard-v2-cards-look
description: The new dashboard's shop and other cards as drawn (pictures 16-19, V5 #8315): what each card draws, the data added for it, the differences kept on purpose
---

## How do the shop cards draw?
- Ecommerce summary and Sales map: the figure in the name gradient (`--pgd-name`), its period line at the far end of the same row. The week's revenue in amber bars (`--pgd-amber`), today's in `--pgd-warn`, the day names under them (`days`, from `shop.php`); best sellers as ruled rows, name over its pieces (`qty`), the money at the end (`money`).
- Sales map: regions and legend in fuchsia steps (`--pg-dash-map-hue`, the top step `--pgd-map-top`, so light keeps the order), its region rows' bars fuchsia too.
- Orders: "#number · customer", the state and its age under it in the state's tone, the amount at the end; no pill. Operations Desk: plain 13 px rows, each ruled above.
- Offers: coupons, a dashed amber frame (`--pgd-coupon-line`), the discount (`off`, `pg_dashboard_v2_offer_off()` from the first action: percentage, amount, free shipping as the currency's 0, none for a free gift) in its own box, the code monospaced.
- Exchange rates: a currency per line, code, name, the week's line amber when the rate went up and cyan when down, the rate over the toned change.

## How do the other cards draw?
- Online engagement: the count in a 60 px green glow, `label` ("On the site now") beside it with the full sentence visually hidden, green minute bars (the last five bright), pages with a live dot; with nobody on the site the line as a heading and the last visits, each with a muted dot (`dashboard-v2-engagement-card.md`).
- My notes: a pale amber note (`--pgd-note`). File management: an 88 px ring, images cyan, documents violet, other yellow, free a faint track; JPG-type boxes cyan, PDF red. Google Analytics: sources indigo, cyan, pink, grey. Performance: the ring is a half gauge (upper half of a 132 px dial, green into cyan).

## What differs from the pictures on purpose?
- Today's bar is the last one (the picture lights Friday); the period stays in the pill.
- The New badge ("3 new") is not drawn: no card sends a count for it yet.
- Subscriptions has no card (not built); the Add card tile and edit mode are V3's.
- My tasks keep the panel's check box; My notes keep their hint line and resize.
- The pipeline card keeps its funnel icon (category colour of V1).

Related: docs/memory/ui/dashboard-v2-looks.md, docs/memory/ui/dashboard-v2-shop-cards.md
