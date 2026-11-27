---
name: designer-frame-tidy-drawing
description: Drawing of the Page Design Studio editor's frame (2026-11-26): the one bar, the left column's heads, the bottom panel, the status bar; which board is which step of designer-frame-tidy
---

## Where is it?
- `docs/memory/designer/designer-frame-tidy-drawing/`: `frame.html`, `drawing.css`, `frame-dark.jpg`, `frame-light.jpg`. The editor's chrome is always dark: both pictures show the same editor, only the sheet around it follows the theme.
- Drawn by the architect for the frame tidy, built in #11956 (FR1), #11973 (FR2), #12007 (FR3), #12031 (FR4), #12054 (FR5). Owner (2026-11-26, message 14316): the visual designer is the heart of the software; its clutter must go. Poll 233: "Evet, sırada o", the editor's frame after the New design window and the home.
- The four areas of plan designer-panels (Options panel, Overview tree, page strip, canvas bar) are on it only so the frame can be read; their own files stay their rule (`designer-panels-overview.md`).

## Which board belongs to which step?
- FR1: F2 the bar, F3's View menu. FR2: F4. FR3: F5. FR4: F6. FR5: the one bar of F1, F2 and F6, and F3's menu. F7 lists where each control stands.

## What does the sheet fix for the bar?
- One row of 48 px in three zones: the menu button and the design's menu; Add Page, the page strip, Pages; then who else is here and the experiment chip, a line, Undo and Redo, a line, Assistant, Preview, Publish. Publish is last and the only filled button.
- The menu button's picture is `bi-list` (shut `bi-x-lg`): the sidebar picture said "a side panel". Undo and Redo are 28 px icon buttons.
- Clicks leaves the bar for the row "Clicks on this page" of the canvas bar's View menu, a group before Side by side, shown to whoever got the button; on, it counts in the View button's badge. Also a command palette row.
- With FR5 the panel's top bar is not drawn in the editor: Search, Quick note, Help for this screen, Site Settings and Notifications are one row at the head of the main menu's drawer under the logo; "Go to site" and the account row are its foot; an unread notification puts a 7 px dot on the menu button. Every other screen keeps its top bar.

## What does it fix for the left column?
- The palette's head is the right column's tab holder in a 40 px row: Components, Shared, System as words, then the dock button. The title row "Components" is gone. Under 230 px a tab that is not open is its rail icon, the word in its tip.
- The list switch and each list's search stay; "Recently used" is a quiet label with the link "Clear".
- The Overview's head is one 40 px row: the name (a press shuts and opens it, the caret at the right) and Tree / Notes as a small pair with the notes' count. Under 230 px the name is its icon.
- Nothing in the frame is written in capitals: heads and tabs are 12.5 px in sentence case.

## What does it fix for the bottom panel and the status bar?
- Heads of 30 px: HTML, Attributes, Styles, the selected element's tag beside the last two. The chevron that shuts the panel stands at its right end, where the shut strip has its own.
- With nothing selected Attributes and Styles say "No element selected" as one line at the top. Under 460 px the Styles head keeps "Create" as a word; "Export to a File" and the rules' search are icon buttons.
- Status bar, 24 px: the path; "Saved 19 hours ago by admin", the line with its verb; Help with its word; Validate showing its result in its place.

## What stays?
- K1 to K12 of `designer-panels-keep.md`; every id a script, a test or the first tour reads, and every shortcut; no part loaded at opening that was not before; colours only as `--sd-*` tokens; the widths 250 and 336 px; docking below 992 px; the bottom panel's three parts and its docked strips.

Related: docs/memory/designer/designer-left-column-heads.md, docs/memory/designer/designer-bottom-panel-heads.md, docs/memory/designer/designer-panels-overview.md, docs/memory/designer/designer-sticky-panel-layout.md, docs/memory/ui/main-menu-drawer-only.md
