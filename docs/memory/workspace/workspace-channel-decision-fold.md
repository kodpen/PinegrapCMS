---
name: workspace-channel-decision-fold
description: A decision longer than about eight lines on a channel's Decisions tab is folded behind Show more / Show less (foldLong() in workspace.js, TD1)
---

## How is a long decision folded?
- `loadList()` gives each card's own text (`.ws-msg-main > .ws-msg-body`, not a quote in it) `.ws-folded` (`max-height: calc(1.55em * 8)`, a fade at the foot), then `foldLong()` reads every height at once and drops the class where the text fits.
- A longer one gets a ghost `pg-button` Show more under its text (`pane_show_more` / `pane_show_less`, `aria-expanded`); the head line with Show in conversation stays above the fold.
- The fold is about eight lines; the architect (2026-11-19) read the owner's "if a decision is long, let it fold" that way, to change on his look (`workspace-channel-panes.md`, TD1 #10396).

## Tests
- `tests/workspace_channel_look_test.php` (`test_workspace_channel_panes_long_decision_is_folded`).
