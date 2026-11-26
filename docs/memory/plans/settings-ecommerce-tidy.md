---
name: settings-ecommerce-tidy
description: Plan (step TR4 of settings-tidy-round): Site Settings › Ecommerce redrawn as folded rows, its ten cards without popovers or plain radios, steps EC1 to EC6
priority: 6
---

## What is asked?
- Owner (2026-11-21, 2026-11-26): old settings screens made modern, the tidy rounds going on (`settings-tidy-round.md`). Ecommerce is the oldest pane left: 127 fields in ten cards, switches that open popovers two deep, plain radios, one long scroll (`settings-tidy-census-order.md`).

## What is there today? (`includes/settings/commerce.php`, 1,041 lines, read 2026-11-26)
- Cards in this order: Store (15 fields), Shipping (12, with the carriers' fields drawn by `pg_carrier_api_settings_fields()`), Stock locations, Address Autocomplete (`pg_addr_settings_fields()`), Gift Cards & Rewards (13), Payment Methods (28, eleven radios), Reference Price, Affiliate Program, Referral programme, Product Feeds.
- Keys are on Connections; the cards hold behaviour and lead there (`ui/connections-one-place.md`). How a lead hands over is `settings-crossings-rule.md`, not this plan.

## What must it never do?
- Change a field's name, id or value, a settings key, a card id, `commerce.save.php`'s checks or what a save writes: only how the pane is drawn changes. One Save a pane.
- Lose an anchor: a field or card that moves to a new row gets its line in `pg_settings_legacy_anchors()`; the registry's card labels and keywords follow.
- Hide a card of a feature that is off (`ui/settings-card-save-rules.md`), open a second dialog (a drawer instead), or move a setting to another pane.
- Change behaviour. A contradiction a step meets goes to the architect; it is not fixed in passing.

## How is the pane drawn?
- Folded rows by `pg_settings_fold()` (`ui/settings-fold-rows.md`), as Communication is (`ui/settings-communication.md`). The rows, what each says closed and what it holds open: `settings-ecommerce-tidy-rows.md`.
- "Enable Commerce" stays at the pane's head, above the rows, with its line while the store is off (`ui/settings-features.md`: the ERP save needs it there).
- Inside a row: no popover and nothing more than one fold deep; a switch that showed a popover shows its fields under it as a group; every switch is `pg-switch`, every plain radio a `pg-seg` or, with more than four choices or a help line each, a list of radio rows; labels in sentence case; one line of help a field; at most eight fields before a group label.

## Which steps? (one at a time: one file; M about 1,000 product lines; tests red first)
- EC1 (M) done (#11868): The fold: the rows' order, names, icons, lines and pills as pure functions in `includes/fn/commerce_settings_fold.php` with `tests/settings_commerce_fold_test.php`; the pane drawn through `pg_settings_fold()`, the purpose line, the cards inside unchanged. Gift Cards & Rewards is cut in two cards here (`pgset-giftcards`, new `pgset-rewards`) with their anchors.
- EC2 (M) done (#PR): Store and Payment methods opened (`ui/settings-ecommerce-store-payments.md`).
- EC3 (M) after EC2: Shipping, Address autocomplete, Stock locations opened; the old buttons of `carrier_api.php`, `carrier_labels.php`, `address_autocomplete.php` and `geo.php` drawn into them.
- EC4 (M) after EC3: Gift cards, Rewards, Reference price opened.
- EC5 (M) after EC4: Affiliate programme, Referral programme, Product feeds opened; the feed's Ctrl multi-select becomes a list of ticked groups.
- EC6 (S) Close-out: `ui/settings-ecommerce.md` (and a second file past 4 KB), the survey's Ecommerce lines corrected, this file and its rows file deleted. No `[NEW]` line: it is the open version's tidy.

## What waits for the architect?
- Seller identity is in General › Organization and again in Product feeds (`settings-tidy-census.md`): EC5 writes down what each is read for and asks before joining them.

Related: docs/memory/plans/settings-tidy-round.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md
