---
name: decisions-made-workspace
description: Decisions list, workspace part: a typed @username in /task, a flow change box's rights, bulk product cards, where the Workspace list of channels and places sits
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

## Where does the Workspace list of channels and places sit?
- In the main menu's Workspace panel only, on every panel page, for everyone the workspace admits; the channel screen has no sidebar column of its own and the other workspace screens no icon rail or drawer (#2545).
- On workspace screens the panel opens by itself while the menu shows its labels (on a phone the drawer opens on it); on the mini rail a click on the row widens the rail over the screen. Elsewhere it is the same list with links, loaded on the first click of the row; collapsing a section works everywhere; New channel and the message search take the person to the channel screen; pin, drag into a group and the right-click menu are the channel screen's.
- Owner decision (2026-10-28, answering the four questions of #2545: "Evet, kendiliğinden", "Kaldırılsın", "Aynı liste, bağlantılarla", "İlk tıklamada"; and "kanallar ve diğer öğelerin erişime göre gizlenme olayı atlanmasın"): one place to find a channel from any screen, without making every panel page heavier (the discussions alone cost up to ~150 queries), and nobody sees a channel, group, discussion or place their access does not open.

Related: docs/memory/workflow/decisions-made.md
