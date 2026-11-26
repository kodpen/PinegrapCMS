---
name: designer-frame-tidy
description: Plan (owner 2026-11-26): the Page Design Studio editor's frame made plain: one bar over the page, plainer heads in the left column and the bottom panel; steps FR1 to FR6
priority: 8
---

## What is asked?
- Owner (2026-11-26, agent channel message 14316): "görsel sayfa tasarımcısı yazılımın kalbi kargaşa azalmalı, anlamlama ve kullanıcı deneyimi artmalı"; poll 233 (message 14326): "Evet, sırada o", the editor's frame after the New design window and the home.
- Owner (2026-11-26, agent channel poll 234, message 14331): "Evet, tek çubuk": the panel's top bar is not drawn in the editor, its tools stand in the main menu's drawer (FR5); every other screen keeps its top bar.

## What is wrong today? (counted on dev at 1440, 1024 and 390 px, 2026-11-26)
- Three bars stand over the page: the panel's top bar (50 px, nothing on it about the page), the page bar (47 px), the canvas bar (44 px).
- The page bar's right end mixes a switch (Clicks), two small undo buttons and three labelled buttons; its first button shows a sidebar and opens the main menu.
- The left column has eight head rows, "Components" three times, five heads in spaced capitals.
- The bottom panel's shut button is inside its first part and the Styles head runs out of its part. The status bar's "19 hours ago by admin" has no verb.

## What is drawn?
- `designer/designer-frame-tidy-drawing.md`: the sheet `frame.html`, boards F1 to F7. Where this file and the sheet differ, the sheet wins.

## What must it never do?
- Lose a line of `designer/designer-panels-keep.md` (K1 to K12), or change the page strip's steps, the canvas bar's zones and folds, the Overview's rows or the Options panel.
- Remove or rename an id the editor's scripts, a test or the first tour read (`designer/designer-first-tour-model.md`), or a shortcut.
- Load a part at opening that was not loaded before, write a colour that is not a `--sd-*` token, or a label without `php tools/tr_add.php`.
- Take the top bar from any screen but the editor.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- FR1 (M) after HT4 has landed: the bar, boards F2 and F3: the menu button's picture; Undo and Redo as 28 px icon buttons; the right end's sequence and its two lines; Clicks as the View menu's row "Clicks on this page" and a command palette row, `#sd-clicks-btn` kept on the row.
- FR2 (M) The left column's heads, board F4.
- FR3 (S) The bottom panel and the status bar, board F5.
- FR4 (S) Narrow, board F6; the PR shows each of the four areas beside its drawing at 1440, 1024 and 390 px.
- FR5 (M) The panel's top bar not drawn in the editor: its five tools at the head of the main menu's drawer, "Go to site" and the account row at its foot, the dot on the menu button. `includes/fn/output.php`, `backend.src.css`, `backend.src.js`; tests `menu_drawer_screen_test.php`, `panel_header_phone_test.php`.
- FR6 (S) Close-out: `designer/designer-left-panel-hide.md`, `designer-sticky-panel-layout.md`, `designer-palette-tabs-and-blocks.md`, `visitors/page-clicks-designer-layer.md` and `ui/main-menu-drawer-only.md` corrected, the readings below moved there, this file deleted.

## Which files?
- `includes/designer_screen.php`, `assets/js/style_designer.src.js`, `assets/css/style_designer.css`, `assets/js/style_designer_clicks.js`: one screen's files (`workflow/coordinator-worker-steps.md`). A step waits for the drawing's merge, never for a yes.

## Which readings does the architect fix? (2026-11-26, agent channel request 3176)
- Clicks is a switch over the canvas, so it is a row of the View menu; no entry of `workflow/DECISIONS.md` binds the button's place.
- The title rows of the palette and the Overview leave; the list switch stays (owner, 2026-11-11). The bottom panel keeps its three parts side by side.

Related: docs/memory/designer/designer-frame-tidy-drawing.md, docs/memory/designer/designer-panels-keep.md, docs/memory/plans/designer-home-tidy.md
