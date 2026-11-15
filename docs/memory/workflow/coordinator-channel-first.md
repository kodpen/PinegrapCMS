---
name: coordinator-channel-first
description: Channels, the agent channel and their API side go before panel look work (owner 2026-11-15, agent channel message 5039 and poll 48): which plans those are, when a look step is taken
---

## What goes first? (owner, 2026-11-15, agent channel message 5039)
- His words: "geliştirme devam ediyor. Kaldığınız yerden devam edebilirsiniz. arayüz geliştirmelerinden önce ajan kanalları ve kanalllarla ilgili özellikleri öne alalım. api taraflarını tamamlayalım."
- Since the priorities (`plan-priority.md`, his message 5159) the order is a number: the channel and API plans are 6 with his other requests, the panel look steps are 2.
- The channel plans: `poll-agent-questions`, `agents-card-settings`, `channel-profile-cards`, `channel-api-gaps`, `agent-work-features`, `agent-channel` with `agent-channel-trial`, `claude-plugin-bundle`, `github-channel-connection`.
- Panel look steps: `panel-old-screens*` (SH, KS, S and group steps) and `report-screens`.
- A panel PR already open is finished by its own session, looked at and landed as usual.

## When is a panel look step taken? (owner, 2026-11-15, agent channel poll 48)
- The question (channel message 5065, decision line 5091): may a place that stands idle because no channel or API step is free take a panel look step. His vote: "Evet, arayüz adımı alsın (Önerilen)".
- So a place is never left empty for the order's sake: with no step of a higher priority free, it takes a look step.

## Is this a pause like the design templates'?
- No, an order: `tools/free_work.php` lists the panel steps as before, and a coordinator leaves them while anything above is free.
- Design templates stay paused as `coordinator-no-plans.md` says.

## How long?
- Until the owner says otherwise.

Related: docs/memory/workflow/plan-priority.md, docs/memory/workflow/coordinator-no-plans.md, docs/memory/workflow/coordinator.md, docs/memory/plans/channel-api-gaps.md
