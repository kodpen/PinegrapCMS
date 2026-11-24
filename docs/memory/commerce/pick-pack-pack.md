---
name: pick-pack-pack
description: Orders › Pack (pack.php): access, the order code and the address list, a scan's verdict and its words, the checked pack's label, marketplace accept, audit line and order.packed
module: pack
---

## Who opens the Pack screen?
- `pack.php`, gated as Pick waves (`pg_pick_validate_access()`): role < 3, the orders right or "Packing only". Menu: "Pack" in Orders after Pick waves; a packing-only user reaches it from the Pick waves toolbar ("Pack", quiet). No price, total or e-mail is read.

## How is an address opened?
- First state: "Scan the order's code" (`request=find`): `pg_pack_order_code()` trims and drops a typed "#", then `orders.order_number`; only addresses `pg_pick_waiting_addresses()` returns (complete or exported, paid, 90 days). One address opens `pack.php?ship_to=N`; two give one button each ("Address 1").
- "or pick an address": `pg_pack_choices()`, paid, not shipped, not packed (in a wave or not), oldest ship-by first, at most 300.
- A GET writes nothing (`pg_pack_read()`); the `order_packs` row is made on the first scan (`pg_pack_open()`, INSERT IGNORE on the unique `ship_to_id`), with that packer and the address's open wave.

## How is a scan counted?
- `request=scan` under a per-address lock: `pg_barcode_scan_product()` (barcode, then product code), `pg_pack_product_facts()` (variant group: the first select-type group, as the feeds read it; options; Recycle Bin), then `pg_pack_scan_verdict()` and `pg_pack_apply_scan()`. Counts go to `scanned`, `refused`, `units`, `scan_lines` (`pg_pack_scan_lines_store()`).
- Lines: `pg_pack_lines_build()`, one per product with units summed; a line whose product is gone stays apart and cannot be scanned.
- Words: `pg_pack_verdict_text()`; a wrong variant names both option sets ("This is Size: M; the order wants Size: L"), or a general sentence when either has none.
- The screen (`assets/js/pack.js`) keeps the field focused; a refused scan turns its row red two seconds with a low tone; a full line turns green and sinks. Camera: `BarcodeDetector` with Code 128, EAN, UPC, Code 39, QR.

## What follows a checked pack?
- All units scanned: status packed, `packer_id` (who scanned the last unit), `packed_at`. Then `pg_pack_after_packed()`: one `pg_audit('pack', 'order_pack', …)` line; the label through `pg_shipment_label_create()` when the address has no tracking number and the check passes (no service, an existing label: nothing said), opened in a new tab; each Created, not accepted marketplace package of the order accepted (Picking) with `approve_lines`, as the label and invoiced paths do; then `order.packed`.
- `mp_sync_approve()` was not used: it acts only while the account accepts by itself, which the job already does; a pack is a staff action, as Create Label is.

## What does order.packed carry?
- `pg_pack_event_payload()`: id, order_number, ship_to_id, wave_id, packer_id, units, scanned, missing (product_id, units); no customer data. In the webhook catalogue and samples, the Workspace events, the flow start events (Orders area) and their outputs (units, wave).

## What is not built?
- The site switch that wants a checked pack before a label: `pick-pack-label-gate.md`. Not opened in a browser or run on a database; the pure parts are pinned by `tests/pack_screen_test.php`. "Short…", `order.pack_short` and the Packing card: `pick-pack-short.md`.

Related: docs/memory/commerce/pick-pack-rules.md, docs/memory/commerce/pick-pack-waves.md, docs/memory/commerce/pick-pack-tables.md
