---
name: designer-audit-contrast-opacity
description: How the designer audit's contrast check (PgContrast.check(), assets/js/pg_contrast.js) counts opacity on the text and its ancestors, and why
---

## How does opacity enter the contrast ratio?
- `check()` measures what is drawn, not the colours the style names. From the text's element up to its backdrop element (not including it; `backdrop()` gives the first opaque one) the opacities multiply into the text's alpha: they fade the text alone over the backdrop. At the backdrop element and every ancestor above it, an opacity below 1 fades text and backdrop together over what lies under that element (`backdrop(m.parentElement)`; a picture there means the text is not judged, null).
- The arithmetic is the pure `PgContrast.throughOpacity(fg, bg, text, groups)`; an editor box (`opts.ignore`) counts as opacity 1. An opacity of 0 on the text's own element still means not shown (null).
- Before PR #1109 (issue #779) only `opacity: 0` was looked at. Bootstrap's `opacity-75` white text on `text-bg-secondary` (the hello-pinegrap CTA, the clinic band) passed at 4.69:1 while it is drawn 3.41:1; body text in a box at opacity .5 read 15.43:1 (drawn about 3.1:1).

## How was it measured?
- Headless Chromium with Bootstrap 5.3.8: CTA 4.69 → 3.43, half-transparent box 15.43 → 3.12, white on a primary band at opacity .5 4.50 → 2.06, plain body text 15.43 unchanged. The figure is within a few hundredths of the pixels' (the browser rounds channels to whole numbers).
- Tests: `tests/contrast_test.php` (`test_contrast_script_through_opacity`, `test_contrast_check_reads_opacity_on_the_way`).

Related: docs/memory/designer/designer-audit-contrast.md
