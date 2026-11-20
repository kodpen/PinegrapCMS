---
name: auto-dialog-pop-ups
description: Start file for pop-ups (Auto Dialogs): the owner's decision, the browser's <dialog>, consent wait, Opening card, designed content, Pop-ups blocks, counts, cart touch; which file holds what
module: auto_dialogs
---

## What is it?
- Pop-ups on a site are Auto Dialogs (`auto_dialogs` table, switch `config.auto_dialogs`, Features card): no second pop-up system. A row opens a centred dialog, a bar or a corner card, showing a web address in an iframe or a pop-up designed in the visual designer.
- Built 2026-11-15 to 2026-11-20 in PRs #9788 (census) to #10570.

## Which decisions bind it?
- Owner (2026-11-15, agent channel poll 51 and message 5139): pop-ups grow out of Auto Dialogs with new columns as needed, the jQuery UI dialog on sites goes, the screens get the panel's look; counts in a rollup table; no pop-up opens before the cookie notice is answered (`workflow/decisions-made-product-56.md`).
- Rows stored before keep working with their settings untouched: every new column's default reads as the old behaviour, and `software.open_dialog()` stays for a site's own script.
- Columns: `workflow/decisions-made-schema-23.md` (open settings), `schema-28.md` and `schema-29.md` (content), `schema-22.md` (counts); the counts' consent rule: `workflow/decisions-made-product-60.md`.

## Which file holds what?
- The site's dialog, the pick in the browser, the one cookie `pg_popup` and the consent wait (#9805, #9852): `auto-dialog-browser-dialog.md`.
- When, to whom and where it opens (Opens, Scrolled %, Shown To, Place; #9851): `auto-dialog-open-settings.md`.
- What it shows, address or designed pop-up, and "What does it show?" on the screens (#10450, #10530): `auto-dialog-content.md`.
- The Pop-ups blocks and the `data-pg-popup-*` marks (#10570): `designer/designer-blocks-popup.md`.
- The Auto Dialogs list, Create and Edit screens (#9905): `ui/auto-dialog-screens-look.md`, `ui/auto-dialog-shared-form.md`.
- Shown, closed, taken per day (#9877): `visitors/auto-dialog-counts.md`. A take-up with a cart as a recovered-carts touch (#9926): `commerce/cart-recovery-whatsapp-page.md`.
- Also reading the table: `install/index.php`, `includes/fn/innodb.php`, `list_table.php`, the panel menu and search, `get_help_url.php`; both starter backups hold rows 2 and 3, disabled.
- The API fields: `api/api-design-dialogs-regions.md`. The cookie's catalogue row: `cookie-consent-catalogue.md`.

## What still uses jQuery UI?
- On sites: the edit-region dialog, share comments and the kiosk dialog (`frontend.src.js`); jQuery UI still loads on every site page. In the panel: the theme designer.
- So the `ui-dialog` rules in `frontend.src.css`, `livesite.src.css`, `pinegrap.src.css`, `generate_system_theme_css.php` and the 8 backup themes stay: share-comment and kiosk dialogs carry `software mobile_dialog`. None of them reaches an auto dialog any more.

## How is a new part added?
- A new open setting or content kind: a VARCHAR value read through `pg_auto_dialog_open_settings()` or `pg_auto_dialog_content()` with today's behaviour as default; a new column is asked first (`CLAUDE.md`, Working mode 10).
- A change to the site script edits `frontend.src.js` and hand-patches `frontend.min.js` the same way; `tests/auto_dialog_form_test.php` reads both.
- A new ready pop-up block goes into `style_designer_blocks_popup.js` with its close and take marks.
