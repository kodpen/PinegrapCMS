---
name: decisions-made-panel-12
description: Decisions list, panel part 12 (2026-11-20 on): the owner's yes to three drawings: Application Access New application (poll 188), Edit application (poll 189), Assistants and agents (poll 190)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-11.md`.

## Is New application drawn in three steps? (owner, 2026-11-20, agent channel poll 188, channel message 10735)
- "Evet, böyle çıksın", on the drawing `plans/app-access-redraw/app-new.html` (and JPG): 1 What connects (ready starting points), 2 Details and permissions (the permission picker with its bulk buttons), 3 Connect (the keys and the guide).
- Binds: AA1 and AA2 of `plans/app-access-redraw-steps.md` build the sheet as drawn. AA1 #10745 no longer waits as a draft.
- Not chosen: "Hayır, baştan çiz".

## Is Edit application a drawer with three tabs? (owner, 2026-11-20, agent channel poll 189, channel message 10736)
- "Evet, böyle çıksın", on the drawing `plans/app-access-redraw/app-edit.html` (and JPG): A General, B Connect (a guide for each way to connect, Claude Console among them), C Agent (the agent and its channels in one place).
- Binds: AA3 and AA5 build the drawer as drawn.
- Not chosen: "Hayır, baştan çiz".

## Is Assistants and agents gathered as drawn? (owner, 2026-11-20, agent channel poll 190, channel message 10740)
- "Evet, böyle çıksın", on the drawing `assistants.html` (and JPG; `git show 95a7d40a:docs/memory/plans/assistants-settings-tidy/assistants.html`): A the seven parts as folded rows, only one open; B Claude's long set-up guide in a drawer. The dialog at its new size (1240 × 880).
- Binds: AT1, AT2 and AT3 (#10807, #10860, #10864) built the tab as drawn (`ui/settings-fold-rows.md`).
- Not chosen: "Hayır, baştan çiz".

## What do the three have in common?
- The request behind each is in `decisions-made-product-88.md` (Application Access) and `decisions-made-panel-11.md` (Assistants and agents); the owner asked to see the screens before they were built.
- A drawing is the look agreed, not a list of texts: labels and help lines are the step's, each through `lang()`.
- A step's PR names the drawing it followed and what it saw on dev; the drawings go with their plan at its close-out.
