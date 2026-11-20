---
name: look-overview
description: Start file for the look-* files: look A in the panel as a whole, the recipes, which screen families moved to it and with which PRs (plan panel-old-screens), the kept screens, the open work
---

## What is look A?
- The panel's one design language: Inter, the type scale and tone tokens, recipe classes in `assets/css/backend.src.css` (`look-type-and-recipes.md`); widths `look-page-widths.md`; buttons `pg-button` and `pg-icon-button`, tabs `pg-seg` (`look-new-parts.md`). `tools/check_look.php` lets old values only fall (`look-check.md`).
- Family recipes: add and edit `look-edit-screens.md`, `look-edit-field.md`, `look-field-widths.md`; lists `list-table-overview.md`, `look-list-title-settings-tile.md`; settings `look-settings-screens.md`; reports `look-report-screens.md`; the save bar `save-bar-helper.md`.

## Which shared parts moved, and with which PR?
- LM2 #9680 toolbar pill and save bar buttons by role (`look-shared-buttons.md`). SH1 #9727 field label and input (`look-edit-field.md`). SH2 #9757 `.pg-kv` (`look-kv-list.md`). SH3 #9744 ERP lists (`look-erp-lists.md`). SH4 #9725 figure cards (`look-figure-cards.md`). SH5 #9739 icon buttons (`look-icon-buttons.md`).
- SH6a #9746, SH6b #9779, SH6c #9803 the save bar (`save-bar-helper.md`, `save-bar-phone.md`). SH7 #9734 a stacked row's Action label. SH8 #9756 `.pg-embedded-table` (`look-embedded-table.md`). SH9 #9726 list title count, segmented filters, settings tile. SH10 #9755 Marketplace ranks (`look-marketplace-ranks.md`).

## Which screen families moved, and with which PR?
- Commerce: C1 orders #9681 (`look-order-screens.md`), C2 products #9682, C3 customers, offers, gift cards #9691 (`look-people-screens.md` for contacts' rules), C4a Marketplace Products #9690, C4b Marketplace Settings #9694, C5 shipping, tax, currency forms #9710.
- ERP: E1 documents #9695, E2 #9699 and E2b #9701 lists (`erp/erp-list-screens-look.md`), E3 reports #9696 (`look-report-erp-screens.md`), E4 records #9697, E5 imports and settings #9704 (`look-mapping-grids.md`).
- Site: S1 content #9773 (`look-content-screens.md`), S2 people #9692 (`look-people-screens.md`), S3 forms, files, calendars #10314 (`look-forms-files-screens.md`), S4 menus, regions, logs #10316 (`look-menus-regions-screens.md`).
- Site Settings: S5a #10426 (landed in train #10438), S5b #10423, S5c #10456, S5d #10431, S5e #10458, S5f #10485 (`look-settings-categories.md`).
- System: S6a tools #10533 (`look-system-tools.md`, Clean Up too, plan close-out), S6b settings pages outside the dialog #10534 (`look-settings-pages-outside.md`), S6c Translations #10547 (`look-translations-screen.md`).
- Kept screens: KS1 #9721 and KS2 #9754 (`look-kept-screens.md`).

## What binds a change of look?
- Owner (2026-11-15): old screens move to the modern look ("eski sayfaları yeni modern tasarıma geçirme olur").
- A screen's look is approved by the architect from real-panel pictures (light and dark, 1440 and 390), delegated by the owner (message 2645); a new design goes to the owner.
- A move copies the recipes value for value and changes no behaviour, field or text. Where the sheet is silent: `look-sheet-silent.md`. Kept screens: `look-kept-screens.md`.
- Left out: the Workspace, the classic page screens (`look-field-widths.md`), the visual designer.

## What is still open?
- `look-open-work.md`.
