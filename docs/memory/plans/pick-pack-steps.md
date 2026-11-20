---
name: pick-pack-steps
description: Plan pick-pack, steps PP1a to PP7: the pure rules, shelf addresses, waves and the pick list, the pack check, label and marketplace on a checked pack, short picks and measures, API, close-out
---

## Which steps are there?
- PP1a (S) first, no schema: new `includes/fn/pick_pack.php` with the pure parts: the shelf sort, the wave rule's filter, the pick list totals, the scan verdict (good, wrong product, wrong variant, too many, binned), the pack state.
  - `tests/pick_pack_test.php` red first: "A-3" before "A-10", no address last, a cancelled order out of its wave, a variant named, a unit past the line, a short pack. Features: K2, K4, K9, K10.
- PP1b (S) after PP1a, only after the owner's yes to poll 193 (message 10746): the four tables as a migration step in the open version (`release/` rules), the columns of the schema decisions list; the "packing only" column too if poll 193 says yes (`core/` role rules).
- PP2 (S) after PP1b: shelf address on the product's stock card, Change Selected, product import and the inventory API. Feature: K1.
- PP3 (M) after PP1b: Orders › "Pick waves": make by hand or rule, the pick list on paper and phone, the order code on the packing slip. Features: K3, K5, K6, K7.
- PP4 (M) after PP1b: the pack screen (scanner, typed code, camera), the checked pack with its label and the marketplace accept, `order.packed`. Features: K8, K11, K12, K15 (packed).
- PP5 (S) after PP4: short picks with the count fix, the `pack_short` alert in `includes/fn/ops_alerts.php` (shared with sales-watch SW1: check the open PRs) and event, the Packing card. Features: K13, K14, K15 (short).
- PP6 (S) after PP3 and PP4: the API routes and types (`php tools/check_api_schema.php`), the MCP read tool. Feature: K16.
- PP7 (S) close-out: every K number checked against the code with its PR; `commerce/pick-pack.md` written, `commerce/stock-locations-model.md` and `commerce/shipment-labels.md` pointing to it; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- PP1a can start before the poll's answer. After PP1b: PP2, PP3 and PP4. `includes/fn/ops_alerts.php` is also entered by `plans/sales-watch.md` SW1 and the delivery-tower plan's DT2: check the open PRs before claiming.
