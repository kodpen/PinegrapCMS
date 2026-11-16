---
name: plan-order-begun-first
description: Order of work after the owner's message of 2026-11-16 (agent channel message 5821): begun plans are finished first, new plans start in freed places, look modernisation waits at priority 2
---

## What did the owner say? (owner, 2026-11-16, agent channel message 5821)
- Owner (2026-11-16, agent channel message 5821): "planları anlastigimiz gibi önceliklendirerek koordinatörlere dagitirsin. Elimizdeki mevcut işleri bitirme sırasında çalışmalardan kaçınmak icin o işlerden alır tamamlariz. Arayüz modernlestirme işleri düşük öncelikli olarak bekler"
- The architect gives the plans out to both coordinators by the scale of `plan-priority.md`. He was told how in agent channel message 5829; the coordinators in message 5847.

## Which step does a freed place take?
- First a free step of a plan already begun: one with a landed step or an open PR.
- The first step of a new plan only in a place for which no begun step is free, and never while an open PR of a begun step holds a file the new step edits. The claim check of CLAUDE.md (the open PRs' files) is how a coordinator sees it.
- Work in hand is finished, never dropped for a newer plan.

## What is look modernisation, and what is not?
- It is the work nobody asked for by name: the old panel screens moved to look A (`panel-old-screens*`) and the design language's remaining steps (`panel-design-language*`). Both carry `priority: 2`; none of their steps is given while a step of 6 is free. A step of theirs already open is finished; no new one starts.
- It is not a look step inside a plan the owner asked for: such a step carries its plan's priority. Asked on 2026-11-16 for PU5 of `plans/auto-dialog-popups.md` (agent channel message 5837): it stays 6, PU6 and PU7 hang on it. The same holds for AS4 of `plans/agents-card-settings.md` and for `plans/plan-drawers-one-look.md`.
- The owner was told this reading in message 5829 and may lower any of them with a word.

## What is the architect's part, not his words?
- "Begun" read as a landed step or an open PR; the file rule; the list of what counts as look modernisation.

Related: docs/memory/workflow/plan-priority.md, docs/memory/workflow/coordinator.md, docs/memory/plans/website-features.md
