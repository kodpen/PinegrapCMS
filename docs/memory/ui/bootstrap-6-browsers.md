---
name: bootstrap-6-browsers
description: What old browsers lose on the panel's Bootstrap 6.0.0-alpha.1 (light-dark(), :has(), the bundle's parse floor), the Escape bridge fix, and the open question of a later prerelease
---

## Which browsers run the panel fully?
- Chrome/Edge 130, Firefox 132, Safari 18 and newer, with one Chromium 126+ catch: two quick Escapes on an animated dialog left `dialog-open` (the scroll lock) on `<html>`. `pg_bs6_bridge.js` re-checks the class on every `close` (`pg-ui-adapter.md`). A third Escape can still close a static `keyboard:false` dialog without `hidden.bs.dialog`; the bridge does not cover that.

## What do older browsers lose?
- Only looks down to Chrome/Edge 120, Firefox 128, Safari 16.4; badly in Chrome 120-122 and Safari 16.4-17.4, which lack `light-dark()`: transparent dialogs and menus, invisible empty inputs and unchecked boxes.
- Function below that: Firefox 121-127 shows toggles and `.active` inverted, 120 and older never open menus (`:has()`); Chrome/Edge up to 119 shows no tick, up to 110 white-on-transparent primary buttons; Safari up to 16.3 loses toggle state, up to 15.5 a closed dialog cannot reopen.
- The bundle parses from Chrome 85, Firefox 79, Safari 14.1. `backend.src.css` uses `:has()` on its own too, so the panel's floor is not only Bootstrap's.
- Size: the alpha.1 bundle is 60 KB gzipped (5.3.8: 24 KB), its CSS 52 KB (31 KB).

## When does the panel leave 6.0.0-alpha.1?
- Open: when to move to a later 6.0 prerelease, and who decides. Until then `assets/lib/bootstrap-6` stays on npm `6.0.0-alpha.1` (`bootstrap-6.md`); its dist is changed only as `workflow/decisions-made-panel-2.md` allows.

Related: docs/memory/ui/bootstrap-6.md
