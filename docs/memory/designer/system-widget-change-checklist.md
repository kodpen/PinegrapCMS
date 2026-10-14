---
name: system-widget-change-checklist
description: Checklist when changing system widgets (legacy comparison, subgroup test, PHP/JS twins, node --check, logged-out live check) plus the escape-$ rule in heredocs.
---

## What is the change checklist?
1. Open the legacy equivalent page and compare output (including product count).
2. Test in a category WITH subgroups; a flat category looks correct either way.
3. Is the rule a PHP ↔ JS twin (defaults, binding-disabled)? Update both.
4. Run `node --check assets/js/style_designer.js` (there is no `php -l` in the sandbox).
5. Verify live on `dev.pinegrap.com` while LOGGED OUT: in edit mode JSON-LD/RSS are deliberately not produced.

## Why must literal `$` be escaped in heredocs?
- In `<<<HTML` heredocs such as `_eo_render_widget_js()`, JS COMMENTS are also interpolated. A comment line containing `$tot_row` emitted `Undefined variable $tot_row` on every render of the page.
- Escape every literal `$` inside a heredoc as `\$`; intentional interpolations are already in `{$var}` form.

Related: docs/memory/designer/system-widget-test-pages.md
