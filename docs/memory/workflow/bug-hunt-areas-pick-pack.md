---
name: bug-hunt-areas-pick-pack
description: The bug hunt of pick and pack (PP1-PP7, 2026-11-24, #11341): six faults fixed, two issues opened for a decision, what was read clean
module: pick_pack
---

## Which area was hunted?
- Pick and pack, PP1 to PP7 having landed: `includes/fn/pick_pack.php`, `pick_waves.php`, `pack.php`, `product_bins.php`; `view_pick_waves.php`, `pick_wave.php`, `print_pick_list.php`, `pack.php`; `assets/js/pack.js`, `pick_waves.js`; `includes/api/resources/pick_waves.php`.
- Looked at: one product at two locations, a short pick, a line packed twice, an order changed or cancelled while picked, stock moved and undone, the scan field on a phone, rights by role.

## What did the hunt's PR fix? (#11341)
- A product moved to the Recycle Bin after the order was paid was refused at Pack ("not on this order"); the pack could only close short. `pg_pack_scan_verdict()` checks the address's lines first.
- The shelf typed before locations existed (location 0) was shown for an order at any location without its own shelf: the picker went to the default location's shelf in another store. `pg_pick_line_bin()`: location 0 only for the default location.
- A wave holding one product at two locations showed two rows that looked the same; each line now names its location when the list spans more than one (screen and paper).
- The phone camera counted a held product again every 1.5 s; now once, again only after half a second out of view (`cameraRead()`).
- A scanner code read while the last was answered was dropped and left in the field, so the next code was glued to it ("not found"). Each code empties the field and waits its turn (`afterAnswer()`).
- A cancelled order leaving a wave left its status as it was: every remaining line ticked still read Open. `pg_pick_wave_status_sync()`.

## Which issues did it open?
- #11346: the short pick's count fix sets the location to the shelf count, though paid, unsent orders still hold units there (taken at completion).
- #11347: a "Packing only" user gets "Access denied" from the label a checked pack opens (`shipment_label.php` gate); a rights decision.

## What was read clean?
- One open wave per address (one `GET_LOCK`), one pack row per address (unique key, `INSERT IGNORE`), scans and short close under the address lock; a second scan of a closed pack says closed. Lines merged per product with stored scans capped.
- A cancelled order: out of the wave, the Pack screen refuses its address. A completed order's lines are not changed after payment (only cart-stage writers), so "changed while packed" is cancellation only.
- Rights: every screen and post through `pg_pick_validate_access()`, token checked on each post, order links only for those who see orders; the API reads with `orders:read`.

## What was not run?
- No database and no browser: the fixes are pinned by `tests/pick_pack_test.php`, `pick_waves_test.php` and `pack_scan_js_test.php` (Node).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/pick-pack.md
