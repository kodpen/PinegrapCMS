---
name: decisions-made-product-41
description: Decisions list, product part 41 (2026-11-09 on): Workspace scheduling in one place (no Scheduled flow window, one flow list); the new dashboard's System Status card: look, lights off, chips in sight
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files.

## How is scheduled work set up and listed now that scheduled actions are flows? (2026-11-09)
- Owner (2026-11-09, as a selectable list, each time the recommended answer; plan `plans/workspace-scheduling-one-path.md`). Reason: options that still looked like the old split made the software look confusing. Scheduled work is set up in one place and listed in one place.
  - It is set up only on the flow canvas, with its "At a time" start. The composer's Add › Scheduled flow window goes: it could only post one message in the channel at a time. Add › New flow opens the same gallery and canvas with the channel as home, and the gallery gets a "Message to a channel at a time" template. Flows already made with the window run on. This replaces answer 7 of the merge of 2026-10-27 (`workspace/workspace-flows-scheduled-merge.md`) and the note of 2026-11-08 that the quick window stays for the simple case (`workspace/workspace-flow-layer.md`).
  - One list. A converted action is an ordinary row of the flow table for every staff member. The table's start column gives the next run of a flow that starts at a time. The "Converted from scheduled actions" group goes. Run now moves to the flow's own screen and is offered on every flow that starts at a time. Rows the conversion refused stay at the end of the list.
  - The canvas palette offers "Start a flow" only. "Start a scheduled action" boxes already saved keep running and stay editable; the conversion made them from old trigger steps.
  - Add › Start a flow stays, shown only in a channel where the person may start a flow. It is the panel's only way to run a flow with its tasks and its card in that channel.
- Owner (2026-11-09): the old scheduled actions shipped, but nobody uses them yet. Their useful functions come into flows: the next run and Run now. Nothing is built for the old rows as if they were in use, so they get no mark of their own and no history is moved over; they only keep running.

## How does the new dashboard's System Status card look and behave? (2026-11-10)
- Owner (2026-11-10, in chat and as a selectable list; `ui/dashboard-v2-status-card-look.md`). The gauge itself stays the one of 2026-11-01 (`decisions-made-product-17.md`).
  - With "Lights and shadows" off the gauge keeps its twinkle; only the light around it, the shadows and the backdrop go ("animasyonu geri gelsin", "Kapalıyken de oynasın"). Reason: with the switch off the gauge stood still, which read as its animation being gone.
  - The gauge's colours follow the card style, the dashboard's colour family and the light or dark theme, and the chips with them ("renkleri temaları ile daha uyumlu olsun").
  - The card's header button reads "Changes" ("detaylar yazan düğmeyi, değişiklikler olarak güncelleyelim"): it opens the Release Notes.
  - The chips are smaller and only the urgent ones stay in sight ("altındaki chipleri biraz daha küçültelim sadece acil olanlar sürekli görünsünler"): the failed checks; every other check folds behind one chip.
