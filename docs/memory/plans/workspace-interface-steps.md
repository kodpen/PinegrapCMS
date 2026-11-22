---
name: workspace-interface-steps
description: Plan workspace-interface, steps WI1 to WI3 (the list line): rows and marks, the tools dock and the tasks count, Unread with Mark all as read and the keys; how a step is checked
---

## Which steps are there? (each enters `assets/js/workspace_side.js` and the `.ws-side-*` rules of `backend.src.css`)
- WI1 (M) first: the rows (boards L1, L3). 30 px rows with the 18 px tile (`channelIcon()`, the channel's colour as today's `#`), the count pills, the pin on pointing, the muted mark from `notify`, the pinned part's hairline, section names without capitals (`sectionToggle()`), "N channels you could join" as a fold (`ws-side-open`), the search field's new words.
- In WI1: the draft's pencil. The list reads the `ws-draft:` keys of localStorage through one helper shared with workspace.js (`draftKey()` moved where both scripts reach it; the payload names the site and the reader as that key needs them), and a `storage` event redraws the row.
- WI1's tests: `tests/workspace_side_test.php`, `tests/panel_contrast_test.php` (the tile and pill colours in both themes). Pattern "Workspace list row" on Design examples.
- WI2 (M) after WI1: the tools dock (boards L1, L2): the foot that stays (`navTile()` and the Tools part of `draw()`), the cells four across with their names in every state (no fold; the `tools` key of `ws-side-closed` is no longer read), More as a card over the list from "⋯" (`[data-pg-menu-hold]`, so the widened rail stays open), the phone drawer's foot. `PGWsSide.has()` and `openFor()` keep their answers for the tour (`workspace_tour.js`).
- In WI2: the count on My Tasks. `ws_side_payload()` gets `tasks_due` from one function shared with `ws_home()` (the `due_count` read of `includes/workspace/home.php`, moved out, red test first); on the channel screen the count follows the task changes the screen already hears. `tests/workspace_nav_tools_test.php`. Pattern "Tools dock".
- WI3 (M) after WI2: Unread (board L2): the segmented control, the filtered list, `ws-side-filter`, "Everything is read".
- In WI3: "Mark all as read", a panel action `ws_read_all` in `includes/workspace/actions.php` over `ws_channel_mark_read()` for every channel and discussion the reader is a member of, each to its last message; pure part and red test first in `tests/workspace_read_all_test.php`; no API route.
- In WI3: the keys Alt+Up, Alt+Down and their Shift pair, one listener in the list's script, in place through the channel screen's hooks, by link elsewhere; the order is a pure function with its red test.

## Which steps run side by side?
- None of these three: each waits for the one before it to land. The screen line (`workspace-interface-steps-2.md`) enters other files and runs beside this one.

## How is a step checked?
- Parts are copied from the Design examples screen; a part with no model there is on the plan's boards and joins the screen in its step.
- Before ready: a screenshot round on the sandbox (`bash tools/setup_sandbox.sh`, `ui/panel-screenshots.md`) at 1440 and 390 px in both themes, with at least twelve channels in two groups, against the boards the step names; "Screen check" in the PR, the pictures as a private page, never committed.
- The list is checked on a Workspace screen and on one other panel screen (first click loads it), in the phone drawer, and with the menu narrowed to the rail.
- Every new control is reached and worked from the keyboard; a mouse-only shortcut inside a link follows `workspace/workspace-nested-controls.md`.
- Turkish for every new text with `php tools/tr_add.php`.
- A step that finds it needs a column, a right or an API route stops and asks the architect.

Related: docs/memory/plans/workspace-interface.md, docs/memory/plans/workspace-interface-list.md, docs/memory/plans/workspace-interface-steps-2.md
