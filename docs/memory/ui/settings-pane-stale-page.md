---
name: settings-pane-stale-page
description: A settings pane asked by a page left open across an update: the panel script stamp in the dialog's address, the Reload line instead of the cards, the pure compare (#8787)
module: output
---

## What does a page left open across an update get?
- The dialog asks `pg_settings_pane_url()` (`includes/settings/modal.php`): `settings_pane.php?v=<stamp>`, the stamp of the panel script the page was drawn with, `pg_js_bundle_stamp('assets/js/backend.min.js')` (`core/js-bundles.md`).
- A pane read (GET, after the gate) compares it with the stamp of the files served now, `pg_js_stamp_stale()`. A different one answers `status: success` with the category's title, description, icon and a fresh token, and instead of the cards one `alert theme-warning` line: "The panel was updated after this page was opened." and a Reload button.
- No card is drawn and nothing is handed over: notices a tool left in the `settings` form wait for the reloaded page's first pane.
- Reload is `window.location.reload()` on a `<button>`. The address still holds `#settings/<pane>`, so the reloaded page opens the dialog on the same category.

## Why, and why on the server?
- Owner's first look at the drawer (#8677, 2026-11-11): "baktım tıklayınca hiç bir tepki yok" on a panel page left open across the deploy; after a full reload "Yeniledim, açılıyor" (`workflow/decisions-made-panel-3.md`). The new cards needed the new panel script.
- The line is plain markup in the pane's answer, painted like any pane, so the page's old script needs nothing new to show it.

## When is a page not stale?
- Same stamp, or a page that sent none (drawn before the dialog carried one); `pg_js_stamp_stale()` trims both and needs both.
- An update that rewrites `backend.min.js` (`backend.src.js` in `src` mode) changes the stamp even when its bytes stay the same: one reload more, no harm.
- Not checked: the save (POST; its form came from an earlier read and saves as before) and the search index (`?search=1`).

## Tests?
- `tests/settings_pane_stamp_test.php`: the stamp of addresses, the compare, the panel bundle's stamp, the wiring (the gate before the check, the check before any card).
- Sandbox (#8787): the line on the next category after `backend.min.js` changed, Reload by mouse and by Enter, light and dark, wide and phone. While the guided tour runs, it takes Enter for itself (`pg_tour.js`).
