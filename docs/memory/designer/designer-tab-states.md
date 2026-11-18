---
name: designer-tab-states
description: A designer page tab's states beside its name (D49): changed dot, lock of a page another person edits, home icon, draft pill, end mark; order, tip, narrow form
---

## What does a tab show, in which order?
- `_pgTabState(p, s)` (pure, `tests/designer_tab_states_test.php`) decides it; `_pgTabHtml()` draws it.
- Before the name: the changed dot (accent, shown only while the page is changed), the amber lock of a page somebody else has open for editing, the home page's house. After it: the draft pill, or the schedule badge in its place. Last: the three-dot menu of the open tab, the × of a page never saved, or the muted lock of a page whose folder the operator has no content rights to (that tab does not open).
- `after` is the place for another plan's mark after the name: it goes before the end mark, so a tab reads name, pill, mark, menu.

## Where does "open by somebody else" come from?
- From the presence beat: `_collab.peers` rows with `editing` true and the page's `page_id` (`pg_collab_peers()`). `_pgTabHolder(p)` gives the first one's name. `_pgTabsSyncBusy()` runs after every beat and redraws the strip only when the set of held pages changes, never while a tab is renamed or dragged (the next beat does it).
- The open page held by somebody else carries the lock too, beside the view-mode bar.

## What does the tip say?
- The tab's title is its name, then every state that shows only a mark: "Open for editing by {name}", "Home page", the draft or schedule note, the folder note. The changed dot is not in it: the dot never goes, and it flips without a redraw.

## What are the sizes?
- Tab 30 px, corner 8, 12.5 px. Draft pill 15 px high, corner 6, 9.5 px, warning-soft ground and warning text; the schedule badge keeps its solid outline in the tab's ink. On the narrowest strip (step 6) the open tab's draft pill is its dashed amber outline without the word; the lock, the house, the dot and the clock are marks already and stay as they are.

## Why does a dirty flip refit the strip?
- The dot takes room only while it shows (no reserved blank before a clean name, as the drawing has it), so a tab grows when it becomes changed. `_pgTabSetDirty()` toggles the class and refits the strip in the next frame when the class really changed.
