---
name: combobox-native-size
description: Why the posted select behind a pg_fields.js combobox is pinned to 1px with !important, and the inline width:100% it overrides (#5558)
---

## Why did a narrow edit screen scroll sideways?
- A combobox select (`data-pg-combobox`) is hidden by `.pg-combobox-native` (absolute, 1px, clipped) once `pg_fields.js` draws its toggle. Many forms write the select with `style="width:100%"` (shipping method zones, zone, tax zone, verified address, product group, product builder); the inline width beat the class's `width: 1px`, so the unseen select stayed as wide as its column and, positioned absolute, ran past the viewport: 2px of horizontal scroll at 375px on Edit Shipping Method.

## How is it fixed?
- In `backend.src.css` the class sets `width` and `height` to `1px !important`, which an author `!important` rule wins over a normal inline style, inside `@layer custom` too. The forms keep their inline width for the moment before the script runs. Test: `test_combobox_native_select_size` (`tests/arrival_date_form_test.php`).
