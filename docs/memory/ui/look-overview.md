---
name: look-overview
description: Start file for the look-* files: look A and its sheet, the design-language steps (closed 2026-11-24), the screen families moved with their PRs, the open work
---

## What is look A, and where is it drawn?
- The panel's one design language, "the quiet list" (owner, 2026-11-12, #8842, #8860). The sheet: `ui/look-sheet/` (`sheet.css`, pages as HTML with dark and light pictures); pages and widths `look-sheet-pages.md`, numbers `look-sheet-numbers.md`, dialogs and drawers `look-sheet-dialogs.md`, the report screen `look-sheet-report.md`. A new drawing builds on `sheet.css` (`workflow/architect-draws-new-part.md`).
- In code: Inter, the type scale and tones, recipe classes in `assets/css/backend.src.css` (`look-type-and-recipes.md`); widths `look-page-widths.md`; `pg-button`, `pg-icon-button`, `pg-seg` (`look-new-parts.md`); the live page `look-design-examples.md`. `tools/check_look.php` lets old values only fall (`look-check.md`).
- Family recipes: add and edit `look-edit-screens.md`, `look-edit-field.md`, `look-field-widths.md`; lists `list-table-overview.md`, `look-list-title-settings-tile.md`; settings `look-settings-screens.md`; reports `look-report-screens.md`; the save bar `save-bar-helper.md`.

## Which design-language steps landed?
- DL1 type and recipes #8879, DL1b page widths #8892, DL2 Design examples #8907, DL3 the bell's drawer #8897, DL4 the check #8905. DL5b lists #8921, DL5c cards and save bar #8934 (field widths #9658), DL5d settings #8952, DL5e reports #8981, DL5f Workspace lists #11079. DL6 closed the plan (2026-11-24).
- DL5a did not stand: the dashboard keeps its own drawn look (#8938, `dashboard-v2-looks.md`).

## Which shared parts moved, and with which PR?
- LM2 #9680 buttons by role (`look-shared-buttons.md`). SH1 #9727 field (`look-edit-field.md`). SH2 #9757 `.pg-kv`. SH3 #9744 ERP lists. SH4 #9725 figure cards. SH5 #9739 icon buttons. SH6a-c #9746, #9779, #9803 save bar (`save-bar-phone.md`). SH7 #9734 Action label. SH8 #9756 `.pg-embedded-table`. SH9 #9726 list title, settings tile. SH10 #9755 Marketplace ranks.

## Which screen families moved, and with which PR?
- Commerce: C1 orders #9681 (`look-order-screens.md`), C2 products #9682, C3 customers, offers, gift cards #9691, C4a #9690 and C4b #9694 Marketplace, C5 shipping, tax, currency #9710.
- ERP: E1 #9695, E2 #9699, E2b #9701 (`erp/erp-list-screens-look.md`), E3 reports #9696 (`look-report-erp-screens.md`), E4 #9697, E5 #9704 (`look-mapping-grids.md`).
- Site: S1 content #9773, S2 people #9692, S3 forms, files, calendars #10314, S4 menus, regions, logs #10316 (`look-content-screens.md`, `look-people-screens.md`, `look-forms-files-screens.md`, `look-menus-regions-screens.md`).
- Site Settings: S5a #10426, S5b #10423, S5c #10456, S5d #10431, S5e #10458, S5f #10485 (`look-settings-categories.md`).
- System: S6a tools #10533 (`look-system-tools.md`), S6b #10534 (`look-settings-pages-outside.md`), S6c Translations #10547. Kept screens: KS1 #9721, KS2 #9754 (`look-kept-screens.md`).

## What binds a change of look?
- Owner (2026-11-15): old screens move to the modern look ("eski sayfaları yeni modern tasarıma geçirme olur").
- No pictures go for approval since 2026-11-19: the owner looks on dev (`workflow/look-pictures-none.md`); a new design goes to the owner.
- A move copies the recipes value for value and changes no behaviour, field or text; where the sheet is silent `look-sheet-silent.md`. Left out: the classic page screens (`look-field-widths.md`), the visual designer, the dashboard.

## What is still open?
- `look-open-work.md`, the Workspace leftover included.
