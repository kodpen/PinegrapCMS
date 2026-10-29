---
name: settings-modal-entry-points
description: How Site Settings opens: header gear button, pgOpenSettings, URL hooks via fromUrl, pg_settings_link, legacy anchors, orphaned settings.php, removed hub.
---

## Where is the settings button and where is the dialog printed?
- Own step in the UI tour (`pg_tour_shell_steps()`, `at => 'settings'`, target `#pg_settings_open`). `PG_TOUR_SHELL_KEY` was raised to `shell.3` because the frame changed (everyone sees the tour once more); the "and settings too" sentence left the menu steps.
- The button is in the header next to the notification bell (`pg_settings_modal_button()`). The left menu has a Site Settings row too (slot 20), but it leads to the settings pages, not the dialog: its links carry `data-pg-settings-page`, which `asked()` leaves alone (`main-menu-settings-row.md`).
- Button and dialog are printed in `output_header()`, NOT the footer: the visual page editor (`includes/designer_screen.php`) does not call the footer, so a footer dialog would vanish exactly where it helps most.
- Not printed when `$toolbar` is set (`$toolbar != true` gate): that header is a thin strip in the edited site's frame, no place for a dialog. `output_header_secure()` screens (pickers) never get it.
- Open from anywhere: `window.pgOpenSettings('firewall', 'pgset-waf')`.

## How does a link open the dialog?
- The hook is the address itself. Breadcrumb, toolbar button and dashboard widget come from helpers that print a single `<a href>` and pass no attributes, so "add `data-pg-settings` to every link" cannot be enforced. `pgSettingsModal.fromUrl()` recognizes four forms: `#settings[/<pane>[/<card>]]`, `settings_<pane>.php[#<card>]`, `settings2.php` / `settings.php` (last used pane), old `#pgset-…` / `#pgsub-…` anchors. An attribute is only for a control with no address of its own.
- `href` stays a real screen: that is what the link does where the dialog is not printed, on middle click and in a new tab. Never hand-write the address: use `pg_settings_link($pane, $section)` (`includes/fn/output.php`).
- Without a pane `pg_settings_link()` returns the page of the FIRST category (`registry.php` order) and the dialog opens in it; "last place I looked" is the header gear's job. `pg_settings_hub_url()` was removed.
- Unrecognized addresses are left alone: the pane is validated against sidebar rows, and an address with a scheme is accepted only if it is this origin; `settings_pane.php`, `api_settings.php`, another host's `settings.php` navigate normally.
- A moved card gains an anchor, not a file name: `settings_security.php#pgset-waf` opens the dialog in Firewall because `pg_settings_legacy_anchors()` says where the card is today. When moving a card to another category write its row into that map; otherwise every old link to the card lands on the wrong pane.

## What happened to the old single screen and the hub?
- `settings.php` (old single screen) is orphan and listed in `clean_up.php`: no link, redirect or include anywhere (remaining `settings.php` passes are comments). Deleting it is the operator's decision: whoever runs the Cleanup screen deletes it. After deletion old in-panel links still work (the resolver recognizes them); only a hand-typed address-bar bookmark is lost.
- `settings2.php` (hub) was deleted: the dialog sidebar does the tile screen's job and only eight category pages remain. Old `settings2.php` links are still resolved by the resolver.

Related: docs/memory/ui/settings-modal-navigation.md
