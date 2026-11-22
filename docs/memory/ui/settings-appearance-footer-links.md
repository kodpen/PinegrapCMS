---
name: settings-appearance-footer-links
description: Footer links of Private label branding (Site Settings › Appearance) as list rows with a drawer each; Move up, Move down, Remove; the order function; the pane drawn after a save
---

## How are the footer links drawn?
- Under the group label "Footer links" in the Private label branding row (`includes/settings/appearance.php`, administrator only): one list row a link (link icon tile, its label as title or "No label", its address as the line), Edit and a row menu with Move up, Move down and, under a divider, Remove (danger, asks a confirm). No link: the line "No link yet. Links added here stand in the panel's footer."
- "Add a link" (quiet, plus) while fewer than five links; it opens the drawer of the next row.
- Each row's drawer is `pg-footer-link-<n>` (`pg_settings_drawer()`, `ui/settings-drawer-card-form.md`): "Label" and "Address", boxes joined to the settings form with `form=`, foot button the pane's Save. Not a `pgset-` id (`ui/settings-fold-rows.md`).

## What is posted, and in what order?
- The drawers post today's `footer_link_<n>_label` / `_url`, n being the row's place (1 to 5). The rows come from `pg_private_label_links_order()` (`includes/fn/core.php`, pure): links holding a label or an address, in order, closed up; the rest ''.
- Move up, Move down and Remove are submit buttons `footer_link_action` = `up:<n>`, `down:<n>`, `remove:<n>`: the pane's save runs and `appearance.save.php` passes the post through `pg_private_label_links_order($_POST, action)` before `pg_private_label_config_content()`. A link removed or emptied in its drawer moves the ones under it up. No script of its own.
- Tests: `tests/private_label_config_test.php` (the order), `tests/settings_appearance_fold_test.php` (rows, drawers, five without Add a link).

## Why does the pane read the saved values after a save?
- `settings_pane.php` saves and draws the pane in one request, and the `FOOTER_LINK_*`, `LOGO_URL` constants still hold the file as the request began. Drawn from them, the pane showed the old links (a new link vanished), and the next save wrote the old values back. The save now hands what it wrote to `pg_private_label_saved_values()`, which the pane prefers to the constants (#11092).

## What did not change?
- The ten fields and their constants (`pg_private_label_config_keys()`), the private label gate, and the writer: with the switch off, a save removes every private label define, footer links included.

Related: docs/memory/ui/settings-appearance.md, docs/memory/core/init-constants-and-config-file.md
