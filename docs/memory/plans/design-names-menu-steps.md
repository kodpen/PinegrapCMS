---
name: design-names-menu-steps
description: Steps NM1-NM4 (owner 2026-11-12, #8849): Image Editor named Design Canvas with its own icon, Visual Page Editor named Page Design Studio, Designs, the legacy designer's menu and Ads under Other
---

## What is decided?
- Owner (2026-11-12, #8849; `workflow/decisions-made-panel-6.md`). The Image Editor ("Görsel Düzenleyici") is named Design Canvas ("Tasarım Tuvali") and gets an icon of its own. The Visual Page Editor, in texts the visual designer ("Görsel Sayfa Editörü", "görsel tasarımcı"), is named Page Design Studio ("Sayfa Tasarım Stüdyosu"). Designs are called Designs ("Tasarımlar"), no longer Visual Designs.
- Main menu: the Page Styles row's entries and Ads move under the Page Design Studio row, behind an Other entry; opened, Other shows the present legacy designer menu.
- Only texts and the menu change: no file name, address, API name, setting or code identifier. Ads stays a working feature.

## Which steps are there?
- S under 400 product lines, M about 1,000; each with its tests.

- NM1 (M) The names. Done (#8894). Every `lang()` and `_sdT()` text that names the image editor, the visual page editor or the visual designer, or says Visual Design(s), gets its new wording as a new key with its Turkish (`php tools/tr_add.php`); an old key goes when nothing reads it.
  - A sentence is rewritten, not patched ("Open in the Page Design Studio"). Third-party names stay ("Pintura Image Editor").
  - `designer/designer-turkish-terms.md` and `editor-turkish-terms.md` name the terms; the test over every `_sdT()` key passes. Cut in two by area (panel, designer) if it passes 1,000 lines.

- NM2 (S) The Design Canvas's icon. A two-colour drawing in the main menu's manner (`ui/main-menu-icons.md`, `pg_menu_icon()`), shown wherever the Design Canvas is opened: the File Manager's action, a canvas card in Workspace, the editor's own heading. Drawn first, three candidates on one sheet in `plans/design-names-menu/`; the owner picks, then the code.

- NM3 (M) The menu. Row 21 is titled Page Design Studio; its panel keeps its entries and gets Other, shut until opened, holding the Page Styles row's present entries (Page Styles, Themes and the rest, in their order) and Ads. After NM1. In progress.
  - Rows 17 and 16 are no longer drawn as rows and their slots are retired, never reused; what a pinned 17 or 16 does is settled in the step (`ui/main-menu-categories.md`: operators pin rows by slot).
  - Each entry keeps its own gate (`ui/main-menu-entry-gates.md`): row 21 is drawn for a person who may open any of its entries, Other only when one of its own is there.
  - On those screens row 21 is marked and Other stands open. `tests/menu_category_test.php` follows.

- NM4 (S) Close-out: `ui/main-menu-categories.md`, `main-menu-panels.md` and the term files say the new names and places; this plan deleted.

## Open questions
- None now.
