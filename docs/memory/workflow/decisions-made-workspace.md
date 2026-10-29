---
name: decisions-made-workspace
description: Decisions list, workspace part: a typed @username in /task, a flow change box's rights, bulk product cards, model assistants, the Workspace list, the retired scheduled form
---

## Does a typed @name in /task assign?
- Yes, when it is exactly one team member's username (case and Turkish letters folded); a name nobody has, a prefix (`@a`) or one several people share assigns nobody and the live line warns (`ws_command_typed_assignees()`, `workspace/workspace-command-live-line.md`).
- Owner decision (2026-10-26, #1731): a name typed by hand assigns like a picked one, so a task can be given without opening the list; only an exact, single match assigns, so a guess never gives the task to the wrong person. Only team members are matched, never site members.

## Whose rights does a flow's change box write with?
- The owner's, and only when the person who started the run (`ws_flow_runs.started_by`) may make the same change too. A run an anchor starts (time, event, join) has the owner as its starter, so it is unchanged; the other boxes keep the owner's rights alone.
- Owner decision (2026-10-27, "akışı başlatan kişi yetkisi olmalı", on the change box of #1957): a run share lets a colleague start someone else's flow, and the box then wrote with the owner's rights alone, so the colleague could have a change made, a person's role included, that they may not make themselves.

## How big is a bulk product card, and what does it announce?
- At most 200 rows (`WS_BULK_CREATE_MAX`); more goes through `import_products.php`. Each product made sends `product.created`, as the assistants' bulk updates send their events (`workspace/workspace-bulk-create.md`).
- Owner decision (2026-10-27, «toplu ürün önerin kabul», questions 2 and 4 of the catalogue create plan, done and deleted): a card the person reads before applying stays short enough to check; a subscriber hears each new product the same way whichever door made it.

## Are model assistants licence-gated?
- No: model assistants with the site's own key (`plans/workspace-agent-handles.md`, step 3) run on every site; Pinegrap AI stays Premium-only, separately.
- Owner decision (2026-10-27, #2418): the key and its cost are the site's own, nothing of Kodpen's is used.

## Where does the Workspace list of channels and places sit?
- In the main menu's Workspace panel only, on every panel page, for everyone the workspace admits; the channel screen has no sidebar column of its own and the other workspace screens no icon rail or drawer (#2545).
- On workspace screens the menu is open and the panel opens by itself, narrowed only to the rail (`decisions-made-panel.md`); on a phone the drawer opens on it. Elsewhere it is the same list with links, loaded on the first click; collapsing works everywhere; New channel and the message search go to the channel screen, which alone has pin, drag into a group and the right-click menu.
- Owner decision (2026-10-28, answering the four questions of #2545: "Evet, kendiliğinden", "Kaldırılsın", "Aynı liste, bağlantılarla", "İlk tıklamada"; and "kanallar ve diğer öğelerin erişime göre gizlenme olayı atlanmasın"): one place to find a channel from any screen, without making every panel page heavier (the discussions alone cost up to ~150 queries), and nobody sees a channel, group, discussion or place their access does not open.

## What do Edit and the three starters do once the scheduled form is retired?
- Decided (architect, 2026-10-28, #2753; `workspace/workspace-flows-scheduled-merge.md`, R1b and R2): Edit on an action row the conversion refused (`flow_id = 0`) retries `ws_scheduled_convert()`: converted, it opens the canvas; refused, it lists the problems and offers Duplicate; the row runs on until cancelled; no schema. The `forms`, `orders` and `chain` starters come over with flow semantics: counts read the last day (the texts say so), `chain` is a manual start another flow's start-a-flow box starts.

Related: docs/memory/workflow/decisions-made.md, decisions-made-workspace-2.md (part 2)
