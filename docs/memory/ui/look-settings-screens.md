---
name: look-settings-screens
description: Settings screens in look A (DL5d, #8952): dialog size, rail, head and foot; cards as a title over an inner card of rows, page and dialog; 32 px fields, switch; what is not done
---

## Where is it drawn, and where is the CSS?
- From `plans/panel-design-language/dialogs.html` (the settings dialog) and `basics.html` (the settings card), numbers in `plans/panel-design-language-dialogs.md`.
- The dialog's rail, head and foot: in the old block "The settings modal" of `backend.src.css`, values replaced in place. The cards: block "Look A: settings" at the file's end, and selectors beside the recipes (`.pg-card`, `.pg-card-inner`, `.pg-group-label`, the 32 px field, the switch, `.pg-floatbar`).
- Tokens it added: `--pg-surface-menu` (a rail's surface), `--pg-scrim` (a dialog's scrim), `--pg-shadow-sheet` (a dialog's or drawer's large shadow).

## What does the dialog look like?
- 1040 × 680 (or the window minus Bootstrap's margins), corners 20, the card's gradient, the sheet shadow; the scrim `--pg-scrim` with a 2 px blur. Below 1024 px it is full screen as before (`settings-modal-layout.md`).
- Rail 232 px on `--pg-surface-menu`, padding 18 12: the title "Site Settings" (17 / 650, no icon), the 32 px search field, group names 12 / 550 in the third text colour (no capitals), items 34 px, 13 / 500, 15 px icon; the chosen item on the quiet fill with an inset line and its icon in the accent (no left bar).
- Head padding 20 16 0 24: the category title 17 / 650 and one line of 13 px; help and Close are 32 px icon buttons (`.pg-icon-button`; Close is `bi-x-lg`, no `btn-close`). The phone's list button is an icon button too.
- Foot: a line, padding 14 24; "Close" ghost and "Save" filled (`.pg-button`), no icon on Save; the meta text at the left in 12 px.

## What does a settings card look like?
- `.pg-set-card > .card` is only a frame (no fill, line or shadow); its `.card-header` is the title above, its `.card-body` the inner card (`--pg-surface-inset`, an inset line), padding 16 px across and the row step `--pg-set-row-y` (13 px on the page, 12 px in the dialog) top and bottom.
- Titles are `pg-title-section` in the markup (the header div, or the span inside a header with buttons); the upper-case `text-uppercase h5 fg-primary fw-bold` left every card (Bootstrap 6's utilities beat any panel rule). The search index still reads the `card-header` div.
- In the dialog the title is a group name (12 / 550, third text colour) 8 px over the inner card, corners 14; on the page each card is the look card (`.pg-card`) with 22 px inside it and the title at 17 / 650, corners 16.
- Rows: name 14 / 550, description 13 px in the second text colour, the line between rows as before (`settings-card-row-layout.md`); cards 16 px apart.
- Fields in a card (`.form-control`) are 32 px, corners 9, 13 px, on the field colours; Bootstrap's `.switch` is 38 × 22 with the accent gradient when on, a white knob. `--bs-switch-border-width` is `0px`: a bare 0 made the knob's size invalid and the knob vanished (#8981).

## What about the settings page (settings_<key>.php)?
- Body class `pg-measure-edit`: the content centred at 1120 px. Its save bar is the floating bar from 576 px, "Save" filled.

## What is not done yet?
- The 34 px icon tile at a row's left, the segmented control and small quiet button as row controls: per-row markup, card by card.
- The foot's amber pill counting unsaved changes and "Undo" (a new behaviour, not only a look).
- Buttons inside card headers keep their Bootstrap classes; the page's toolbar (category menu, chips) is unchanged; the page draws one look card per settings card, not one surface for all.

Related: docs/memory/ui/settings-modal-overview.md
