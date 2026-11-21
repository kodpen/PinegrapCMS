---
name: assistants-settings-tidy-steps
description: Plan assistants-settings-tidy, steps AT1 to AT6: the folded rows of Assistants and agents, Claude's set-up drawer, the help lines, close-out
---

## Which steps are there?
- AT1 (M) done #10807: the folded rows (`ui/settings-fold-rows.md`), features T1, T2, T5, T6.
- AT2 (M) done #10860: Claude's row and its set-up drawer (`includes/workspace/claude.php`'s card). Feature: T3.
- AT2b (M) done #10898: Claude's opened row part by part (one column, the fold Application and key, the drawer The latest requests; `ui/settings-claude-row.md`). Feature: T3.
- AT3 (S) done #10864: the help lines of Pinegrap AI, Agents, MCP servers, Voice and Canvas templates; texts with `php tools/tr_add.php`. Features: T4, T7.
- AT4 (S) done #10918: the T numbers checked against the code; the four Workspace settings files corrected; the MCP list's "Not connected" gray.
- AT5 (M): the Pinegrap AI and Voice cards in one column; the right-column parts (Licence, Still missing, tokens, The latest requests; on Voice: Recordings are sent to, Spoken answers are made by) placed where Claude's row has them; long help into a "How does this work?" drawer, the latest requests into their own drawer. Described part by part in `plans/assistants-settings-tidy-screens-2.md` (the architect's docs PR). Starts when that file and #10918 have landed.
- AT6 (S/M): the MCP servers, Workspace agents and Canvas templates lists show at most five rows, then "Show all N" and the part's drawer. After AT5 (same screen, in turn). Its close-out deletes this plan.

## Which steps run side by side?
- After AT1: AT2 and AT3, each in its own files.

## What does each step's PR carry?
- `Decision:` the owner's words in the architect's window, 2026-11-20 (`workflow/decisions-made-panel-11.md`); no migration step; what was seen on dev.
