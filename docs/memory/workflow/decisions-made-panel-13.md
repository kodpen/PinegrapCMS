---
name: decisions-made-panel-13
description: Decisions list, panel part 13 (2026-11-21 on): the dashboard's Assistant card keeps the agent chat while Claude is off, with a notice at its top and a short note when no agent is connected
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-12.md`.

## Does the dashboard's Assistant card keep the agent chat while Claude is off? (owner, 2026-11-21, in the architect's window, with a picture of the card)
- Owner: "bu kart claude kapalıysa ajan sohbetinide kaldırıyor. aslında ikisi bir nevi farklı şeyler claude kapalı olursa tepede asistan bağlantısı yok uyarısı olsun ancak ajan ile konuşma bağlı ajan olduğu sürece açık gelsin. ayrıca ajan bağlı değilse ajan kanalı ve ajan bağlamayla ilgili kısa bilgilendirme olsun".
- So Claude and the agents are two things on the card. Claude off: a notice at the top says so. The chat stays for as long as an assistant other than Claude can be asked.
- With no agent connected the card says in a few lines what an agent and its channel are and where one is connected.
- What it replaces: the card drew only "Connect Claude" while Claude was not ready and hid the chat, the agent questions and the proposals with it (`ui/dashboard-v2-ai-card.md`).
- How each state is laid out: `plans/dashboard-assistant-agents.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/ui/dashboard-v2-ai-card.md
