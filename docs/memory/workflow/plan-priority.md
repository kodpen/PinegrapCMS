---
name: plan-priority
description: Every plan carries a priority from 1 to 10 (owner 2026-11-15, agent channel message 5159): the scale, where the number is written, how a free place picks its next step
---

## What is the scale? (owner, 2026-11-15, agent channel message 5159)
- His words: "bundan sonra planlara 1 ila 10 arası bir önceliklendirme koyalım. 2/10 düşük (şablon tasarımları ve özel talep dışı arayüz iyileştirmeleri), 6/10 özel talepler ve 9/10 acil güvenlik özellikleri ve 10/10 zamanı kısıtlı planlar"
- 10: a plan with a time limit. 9: an urgent security feature. 6: a special request of the owner. 2: design templates and look improvements nobody asked for.
- The numbers between are the architect's, for telling two plans of one kind apart; none is in use.
- Only the owner makes a plan a 10 or a 9: he names the time limit or calls the security work urgent. The architect writes the date beside a 10 in its plan.

## Where is the number written?
- In the plan file's frontmatter: a line `priority: <number>` after `description:`. A plan's steps files follow their plan. The number lives only there; no other file lists the plans' numbers.
- The architect writes the line when it writes or touches a plan.
- A plan without the line counts 6 when the owner asked for it ("owner" and a date in its description) and 2 when it is a design template plan or a look step nobody asked for (`panel-old-screens*`, `report-screens`).

## How does a free place pick its step?
- The highest priority that has a free step; at the same priority the plan asked first.
- Work in hand is never dropped for a higher number: the rule picks the next step. The architect tells both coordinators when it writes a 10 or a 9, and that plan takes the next place that frees.
- An open `bug` issue is not a plan: it is taken as before, ahead of a step of priority 6 or lower (CLAUDE.md, Working mode 9).
- The channel and API plans of `coordinator-channel-first.md` are sixes like the owner's other requests; the panel look steps are twos.
- Design templates stay paused (`coordinator-no-plans.md`); 2 is their number once they run again.
- The pace limits stay: at most three `channel-api-gaps` steps open on each coordinator's side, the queue limit, 8 places a coordinator.

## What does `tools/free_work.php` do with it?
- Nothing yet (2026-11-15): it lists the design templates apart as low priority and the rest in one list. A coordinator reads the `priority:` line in the plan file.
- Reading the line and listing the free steps highest first is a small tools fix the architect gives out; this section is corrected when it lands.

Related: docs/memory/workflow/coordinator-channel-first.md, docs/memory/workflow/coordinator-no-plans.md, docs/memory/workflow/coordinator.md
