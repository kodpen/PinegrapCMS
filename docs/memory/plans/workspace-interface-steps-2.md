---
name: workspace-interface-steps-2
description: Plan workspace-interface, steps WI4 to WI9 (the screen line): the panel's foot and top bar, each screen's bar, the New line, close-out; what holds for every step, the order
---

## Which steps are on the screen line?
- WI4 (S) first: the panel's shell (board S3). The foot at the window's bottom on every panel screen (`output_footer()` in `includes/fn/output.php`, the shell's rules in `backend.src.css`), none on the channel view of `workspace.php`; the top bar's description cut before the search field; no "Ctrl+K" on the top bar's field on a Workspace screen.
- WI4 is checked with `node tools/screenshots/sweep.js` and the kept screens of `screens.json`: nothing but the foot's place may move on a screen that is not the Workspace's. `tests/workspace_screen_frame_test.php` learns the foot rule.
- WI5 (M) after WI4: the bar's rule and My Tasks (board S1): `startTasks()` in `assets/js/workspace.js`; the "Filter" button, its box and the tags line as one helper the next steps reuse; the empty screen's pattern as one helper. Patterns "Screen bar with filters" and "Empty screen" on Design examples; `ui/page-button-bar.md` gets the rule.
- WI6 (M) after WI5: My Notes (`startNotes()`, the "⋯" menu) and Data (`workspace_wtables.php`'s `#button_bar`, printed by the server; its views and search in one line), with their empty screens (board S2).
- WI7 (M) after WI6: Work Calendar (`startCalendar()`), My calendar (`assets/js/workspace_my_calendar.js`), Planning Board (`startBoard()`), Decision Timeline (`startTimeline()`), with their empty screens (board S2).
- WI8 (S) after WI7: the channel's "New" line (board C1) in the conversation's drawing of `workspace.js`: where it stands is a pure function of the read mark the screen opened with (red test first); "Go to new messages" scrolls to it; nothing is written.
- WI9 (S) last: close-out.

## What does the close-out do?
- Rewrites `workspace/workspace-menu-list.md`, `workspace-menu-tools.md`, `workspace-screen-frame.md`, `workspace-channel-catchup.md` and `ui/page-button-bar.md` to what was built, with the PR numbers.
- Corrects the lines of `workflow/decisions-made-workspace-5.md` and `-6.md` this plan replaced, pointing at `decisions-made-workspace-48.md`.
- Shortens the DL5f line of `plans/panel-design-language-steps.md` to what is still left.
- Keeps `workspace/workspace-interface-drawing/` as the reference pictures and deletes the plan's files.

## What holds for every step?
- About 1,000 product lines at most; no schema; every control of today stays reachable.
- A step copies the board, value for value; where the board and the words are silent it asks the architect.
- WI5 to WI7 change no filter's meaning, no list's order and no saved choice: a filter moved behind "Filter" keeps its address parameter and its stored value.
- Checked as `workspace-interface-steps.md` says; a bar is also checked at 1100 px.

## In which order?
- The list line WI1, WI2, WI3 in `workspace_side.js`, one worker at a time.
- The screen line WI4, WI5, WI6, WI7, WI8, one worker at a time: all but WI4 enter `workspace.js`. It runs beside the list line.
- While `plans/flows-editor-next.md` runs, no clash: its steps enter `workspace_flow.js` and the `.wsf-*` rules.
- WI9 after both lines have landed.

Related: docs/memory/plans/workspace-interface.md, docs/memory/plans/workspace-interface-screens.md, docs/memory/plans/workspace-interface-steps.md
