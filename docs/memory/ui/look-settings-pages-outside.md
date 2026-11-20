---
name: look-settings-pages-outside
description: Settings pages outside the Site Settings dialog in look A (S6b): Application Access, Cloudflare Tools, Connected applications, Pair a device; what moved, what stayed
---

## Which screens moved?
- Step S6b of the plan panel-old-screens (#10534): `api_settings.php`, `cloudflare.php`, `connected_apps.php`, `device_pairing.php`. `smtp_settings.php` only redirects to the E-mail sending category (`look-settings-mail-sending.md`). `connections.php` is not S6's (`connections-screen-layout.md`).
- `tools/check_look.php` counts 0 old buttons and tab strips in the four; their baseline entries are gone.

## What changed on Application Access?
- Card titles are `h2.card-header.bg-reset.border-0.pg-title-section`, no icon; a card's side note `pg-text-2`.
- Summary is `dl.pg-kv` (`look-kv-list.md`); Errors in 24 hours keeps `fg-danger` on its `dd`.
- Row and drawer buttons are `pg-button pg-button-quiet pg-button-sm`; Remove, Delete this application, Yes, delete and a device's revoke `pg-button-danger pg-button-sm`. The drawer's Save, New Application's Create and the one-time sheet's Close are the view's one `pg-button-fill`, its Cancel `pg-button-quiet`.
- `api_settings_copy_button()` takes the whole class list; its Bootstrap fallback went.
- The drawer's tabs are `div.pg-seg.pg-list-seg[role=tablist]` > `button.pg-seg-item` with Bootstrap's `data-bs-toggle="tab"`, which moves `aria-selected` (the thumb). The script hides Devices / Webhook events on the buttons (`d_devices_label`, `d_hooks_label`); on a phone the row scrolls sideways.
- Labels are `form-label pg-field-label` (the sheets' upper-case labels too). The sheet's backdrop is `var(--pg-scrim)`.
- The screen's `<style>` block: every text size a type token (10 to 11 px to `--pg-text-count`, 11.5 to 12.5 px to `--pg-text-meta`, 13 px to `--pg-text-2`), weights 500 / 600 / 700 to `--pg-weight-medium` / `-strong`. The list head is 12 px weight 550 without capitals and keeps the second text colour (`tests/api_screens_contrast_test.php`).

## What changed on Cloudflare Tools?
- Period: `nav.pg-seg.pg-list-seg` of `a.pg-seg-item` (`is-active`, `aria-current="page"`).
- The five analytics values are figure cards: `.pg-figures` with `--pg-figures: 5`, `.pg-card.pg-figure` (`look-figure-cards.md`).
- Card titles `pg-title-section`; Audit Logs and Firewall Events `h3.pg-group-label`, no icon. The two log tables are `table table-hover mb-0 pg-embedded-table`.
- Badges are `pg-pill`: success teal, warning amber, info blue, danger rose, secondary gray.
- Card buttons quiet; Clear Cache `pg-button-danger`; the DNS row's Edit and Delete `pg-icon-button pg-icon-button-sm`; the dialogs' Add and Save fill. Add Record stays the page's fill.
- It still opens in the settings dialog, so it takes no measure class (`look-screen-widths.md`).

## What changed on Connected applications and Pair a device?
- A connection's name is its card's `h2.pg-title-section`; "It may:" `pg-group-label`; the lines `pg-text-2`, the last use `pg-text-meta`; "Turned off by the site" a gray `pg-pill`; End connection and End all connections `pg-button-danger pg-button-sm`.
- Pair a device: Show a pairing code and New code fill, Cancel quiet; the second lines `pg-text-2`.

## What stayed?
- The status columns' `top:3.3rem` sticky offset, the drawer's `min(520px,100%)` width, the pairing card's 520 px cap and the QR code's white box: no token names them.
- The permission rows' Read / Write control (`.api-seg`, Bootstrap `btn-check` with the `implied` state) and the New Application kind cards' labels: only their sizes moved to tokens.
- The plug glyph's size on a connection card (an icon outside a tile, `plans/panel-old-screens-rules.md`).

## What was not seen in the panel?
- The sandbox has no Cloudflare token (the screen shows only that error), no device pairing and no OAuth connection; those parts were read from the code and the unit tests.

Related: docs/memory/ui/look-settings-categories.md, docs/memory/ui/look-new-parts.md
