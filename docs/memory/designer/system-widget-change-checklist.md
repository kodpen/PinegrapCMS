---
name: system-widget-change-checklist
description: Checklist when changing system widgets (legacy comparison, subgroup test, PHP/JS twins, three places for defaults, node --check, logged-out live check) plus the escape-$ rule in heredocs.
---

## What is the change checklist?
1. Open the legacy equivalent page and compare output (including product count). Do not guess what is right: the legacy page's output is the yardstick (frontend-conventions-layers.md).
2. Test in a category WITH subgroups; a flat category looks correct either way.
3. Is the rule a PHP ↔ JS twin (defaults, binding-disabled, no-messages types)? Update both.
4. A default layout lives in three places, changed together: the PHP default tree, the JS starter tree and the server fallback (express order: `_eo_default_designer_tree()`, `_buildExpressOrderStarterTree()`, `_eo_render_cart_summary()`). A fix applied in only one of them comes back wrong from the other two.
5. Run `node --check assets/js/style_designer.js` after touching it.
6. Verify live while LOGGED OUT: in edit mode JSON-LD/RSS are deliberately not produced. Test pages: system-widget-test-pages.md.

## Why must literal `$` be escaped in heredocs?
- In `<<<HTML` heredocs such as `_eo_render_widget_js()`, JS COMMENTS are also interpolated. A comment line containing `$tot_row` emitted `Undefined variable $tot_row` on every render of the page.
- Escape every literal `$` inside a heredoc as `\$`; intentional interpolations are already in `{$var}` form.

Related: docs/memory/designer/system-widget-test-pages.md, docs/memory/designer/system-widget-render-rules.md
