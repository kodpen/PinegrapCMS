---
name: screen-described-in-plan
description: A plan describes each screen it adds in words, by the composition rules; no drawing is approved before a step and no screenshot goes to the architect or the owner for a yes (owner, 2026-11-20)
---

## What did the owner say?
- Owner (2026-11-20, in the architect's window, with a picture of a mailbox's drawer in Site Settings › Mailboxes): "yeni tasarlanan sayfaların ayarların çok karışık anlaşılmaz ve modernizasyon kurallarımız dikkate alınmadan yapılmış olması ... yarın bir ajana tekrar tarif edip bunların hepsini baştan tasarlatınca 2x token harcamış olacağız", and "her yeni oturumda sözlerim sürekli atlanıyor".
- What he met: the drawer as one long form with paragraphs of help, no way to remove the mailbox, and a Google account connected on Connections that Mailboxes showed as not connected (#10825).
- The architect first made a drawing the owner approves a condition of every screen step (agent channel message 10947). Owner, the same hour: "çizim onayı için ekran görüntülerini sana atması olayını denedik bedeli ağır oldu. hem proje durma noktasına geldi hemde mimar kotası şişti. bunun yerine planlarda tarifler daha iyi yapılsın". The condition was taken back (message 10954).

## What is the rule?
- No step waits for a drawing, and nobody sends the architect or the owner a screenshot to approve a screen. The class counts of `ui/look-new-parts.md` and `tools/check_look.php` stay.
- How a screen is put together is written in its plan, in words, by `ui/screen-composition-rules.md`: which screen, its parts in order, the fields' groups and tabs, the states and their words, the buttons with their ranks, what removes the thing.
- The plan's author writes it with the plan: the research agent for its plans, the architect for a screen the owner complained of. For a plan written before 2026-11-20 the architect adds `plans/<plan>-screens.md`, the nearest steps first.
- A screen step whose description has not come does not wait: the worker keeps the composition rules and writes how it laid the screen out in ten lines of the PR body.

## Where do a screen's parts come from? (owner, 2026-11-20)
- Owner: "tasarım bileşenleri oradan alınacağı planlarda yazılı olursa sorun çözülür. eğer bu sayfada olmayan bir bileşen için talep varsa o zaman mimara bileşen için tasarım örneği kaydı açılsın. mimar hem ajana hemde design examples a o bileşeni koyar biter".
- From the Design examples screen (`design_examples.php`): a plan's screen description names, for each part, the section it is copied from.
- A part the screen needs and the Design examples screen lacks: the session asks the architect in the agent channel for a design example (the screen, what the part must do, its states) and builds nothing of its own. The architect answers with the part (its name, markup from the look's tokens and recipes, its states); it joins the Design examples screen in the asking step's PR, as the architect writes no product code.
- Its patterns, and which composition rule each draws: `ui/look-design-examples-patterns.md`, `ui/screen-composition-rules.md`.

## What was found that day?
- Look A bound a screen's parts, nothing bound how a whole screen is put together, and none of the thirteen plans written from 2026-11-18 to 2026-11-20 said it either.
- The Design examples screen drew parts only, and its own top card listed drawers, dialogs and the settings modal as not drawn.
- The drawings the owner did say yes to (`decisions-made-panel-12.md`) stay the model of their screens.

## What does the step's PR carry?
- The description it followed; where it left it, a line says why.
- A pattern the step builds for the first time (a folded settings row, a drawer's foot) joins the Design examples screen in the same PR (`ui/look-design-examples.md`).

Related: docs/memory/workflow/screen-follows-drawing.md, docs/memory/ui/screen-composition-rules.md, docs/memory/ui/look-new-parts.md, docs/memory/workflow/decisions-made-panel-12.md
