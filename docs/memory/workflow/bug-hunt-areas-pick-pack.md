---
name: bug-hunt-areas-pick-pack
description: The bug hunt of pick and pack (PP1-PP7, 2026-11-24): the faults fixed, the issues opened, what was read clean
module: pick_pack
---

## Which area was hunted?
- Pick and pack, PP1 to PP7 having landed: `includes/fn/pick_pack.php`, `pick_waves.php`, `pack.php`, `product_bins.php`; `view_pick_waves.php`, `pick_wave.php`, `print_pick_list.php`, `pack.php`; `assets/js/pack.js`, `pick_waves.js`; `includes/api/resources/pick_waves.php`.
- To look at: a pick list of orders with one product at two locations, a short pick, a line packed twice, an order changed or cancelled while picked, stock moved by each step and undone, the scan field on a phone, rights by role.

## What did the hunt's PR fix?
- Written when the fixes land.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/pick-pack.md
