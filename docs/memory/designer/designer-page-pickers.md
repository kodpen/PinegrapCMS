---
name: designer-page-pickers
description: How widget page pickers are built (_swPageOptions/_swPageVal, tab:<key>, tabs without the widget), how unsaved-tab references resolve on save, and why a page may reach the save without its tree.
---

## How do I add a page picker to a widget setting?
- Every page picker is drawn with `_swPageOptions(cur, serverList, {types, test, none, noneValue})` and read with `_swPageVal(el)`; the value is a positive id, `tab:<key>`, or 0.
- Never hand-build `<option>`s: `parseInt(this.value)` turns a `tab:` value into 0.
- The "this design" optgroup lists the `_pages` tabs first. With `types`, `_swTabHasWidget` lists only tabs holding a widget of that type (twin of the server list; form detail adds `test`). An unsaved tab has value `tab:<key>`.
- A picker for a page whose widget is often added later (a detail page) also lists the design's tabs without that widget, under their own optgroup (`otherTabs` label), and draws `_swDetailPageHint()` under it: a warning when the chosen tab has no such widget yet, a note when no page shows the records.

## Why may a page arrive at the save without its tree?
- A widget setting is written at once through `_patchSysCfg(sid, mutate)` (queued per widget, merged into the stored config), before and apart from the design save. A page nothing touched since the last save goes to the save marked `unchanged: 1` and without its tree.
- So save-time code that finds widgets through a page's tree must read the stored tree when none came, as `pg_designer_resolve_tab_refs()` does with `pg_page_tree_json()` (`includes/designer_screen.php`).

## Where are `tab:<key>` values resolved?
- Same rule in two places:
  - Server: `pg_designer_resolve_tab_refs()` / `pg_designer_resolve_tab_values()` (designer_screen.php) write the widget rows and the form's `confirmation_page_id` after pages get ids. So `pg_cf_reconcile_page_form` runs OUTSIDE the page loop, after `$id_map` is filled.
  - Client: `_swResolveTabRefs(keyMap)` in `_pgTabsAfterSave`, BEFORE tab keys are converted to `p<id>`.
- An unmatched key (closed tab) becomes 0. Nothing is added to the renderer: every read does `(int)`, and `tab:` is 0.

## Which pickers belong to membership widgets?
- `redirect_page_id`, `forgot_password_page_id`, `register_page_id`, `login_page_id`; `types` filters tabs that carry the relevant widget. See the membership widget files.
- `welcome_email_page_id` / `reset_email_page_id` (`_swAccountEmailRow()`): `types: ['account_email']` plus `otherTabs`, so the page is picked before its widget exists.

Related: docs/memory/designer/system-widget-type-and-name.md, docs/memory/designer/member-widgets-tokens-and-fields.md
