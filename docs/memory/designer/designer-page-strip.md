---
name: designer-page-strip
description: The Page Design Studio's page strip: the design's menu (Settings, Delete), how the strip makes room on a narrow screen in six steps, what never goes, the "+N" lists, which file holds the rest
---

## Where are the design's own actions?
- The design's name is a menu (`.sd-design-menu`, `#sd-design-menu-btn` in `includes/designer_screen.php`, DP17 #10206): Settings (opens `#styleSettingsModal`) and, under a divider, Delete, which asks as before. The gear and the bin left the strip. Its label follows the name field of Settings; an unnamed design reads "New design". Test: `tests/designer_design_menu_test.php`.

## How does the strip make room?
- It never scrolls sideways and never wraps. The fullest drawing is tried first and the first step that fits is drawn, by the strip's own width (`_pgTabStripPlan()`, `designer-tab-groups.md`).
- Step 1: the chips lose their count. Step 2: the open group's far tabs go behind "+N"; the tabs nearest the open page stay. Step 3: the chips fold from the right into a "+N groups" chip. Step 4: the design's name becomes its icon (name in the tip and at the head of its menu); the box loses the group's name and keeps its colour. Step 5: all chips become one chip ("4 groups"). Step 6: only the open page's tab stays, at least 60 px wide.
- Architect (2026-11-17, message 8798, DP18 #10245): at step 6 the name is cut last. Before it: the Add page "+" leaves the row, the Pages button loses its count (kept in its tip), the draft pill takes its short form (dashed outline, the word in its tip). The box is its colour line.
- Owner (2026-11-17, poll 132, message 8315) approved these steps on the narrow-strip drawing (`page-strip-narrow-approval.jpg`, in git history: `designer-panels-overview.md`).

## What never goes?
- The open page's tab, its state (changed dot, draft pill, lock) and its three-dot menu, at every step.
- The Pages button stands outside the strip at every width (Ctrl+P, `designer-pages-window.md`).
- Architect (2026-11-17, message 8730): with a saved draft and a design name of 15 letters or more, the Preview button beside Publish stands whole on a 390 px screen (`designer-preview-menu.md`).

## What do the chips open?
- "+N" lists the tabs that did not fit, each with its state. "+N groups" lists the folded groups with colour, name and count; a click lists a group's pages. A page opened from a chip opens its group in the strip. A design without groups has its tabs without a box, behind "+N" the same way.
- Architect (2026-11-17, message 8779): a list carries no caption lines; its actions ("8 more pages", "Open the group in the strip") are in the ink of its rows, not a hint's muted colour.
- "+N" and "+N groups": 30 px high, corner 8, 11.5 px, `--sd-accent-soft` ground with `--sd-accent-line`.

## Which file holds the rest?
- Groups, colours, the stored layout, the open box and chips: `designer-tab-groups.md`. Tab states (dot, lock, home, draft pill): `designer-tab-states.md`. The Pages window: `designer-pages-window.md`. Suggested groups: `designer-tab-suggested-groups.md`. Publish approval's marks on the strip: `content-proposals-editor-strip.md`. Opening, renaming, closing a tab: `multi-page-designer-editor.md`.
