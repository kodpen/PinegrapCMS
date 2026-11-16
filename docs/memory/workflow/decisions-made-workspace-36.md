---
name: decisions-made-workspace-36
description: Decisions made, workspace part 36 (2026-11-16 on): the two plan drawers look the same; a question's picture stands in its box; an agent channel's tasks apart, a task's application marked
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Do the "Add to the plan" and the "New plan" drawers look the same? (owner, 2026-11-16, to the architect)
- Yes. His words, with a picture of the "New plan" drawer: "evet planını yaz, iki çekmece aynı görünsün" and "ayrıca bu çekmecede bir sorun var. bileşenleri iyi ama modern".
- Right before it he asked "panoda plana ekle ile takvimlerimdeki yeni plan ayrı motor mu kullanıyor?". They do, and stay so: a plan is never a `ws_events` row (his decision of 2026-11-14, `workspace/workspace-personal-calendar-rules.md`). Only the look becomes one.
- What was built and the rule it left: `workspace/workspace-plan-drawers-look.md` (#9887, #9909).

## What is the architect's reading here, not his words?
- The "New plan" drawer is the model and "Add to the plan" is brought to it.
- The problem he names is the sideways scroll his picture shows, fixed by #9858.
- He sees both drawers side by side before the "Add to the plan" drawer changes.

## Where does a question's picture stand? (owner, 2026-11-16, to the architect)
- In the question's own box. His words, with a picture of a question whose sheet was a message of its own above it: "çizimlerimizde ajan sorusu tek görsel de sorunun solunda, her seçenek için görsel de seçenek içinde olacaktı. ancak sen resmi atıp mesaj etiketi ile soruya dahil ediyorsun".
- The architect's reading, not his words: the approved drawing draws the large picture only from the options' own pictures, so a question gets a picture of its own, large at the left of the options, with the options' thumbnails in their rows. Plan: `plans/agent-question-picture.md`; his look at the built box follows.

## Are agents' tasks kept apart from the team's? (owner, 2026-11-16, agent channel poll 80)
- Yes, both parts. He had asked the architect to find out whether agent questions and tasks, which pile up in the Workspace's general Decisions and Tasks, should be kept apart from the team's.
- Asked "… ajan kanalının görevleri de (kararları gibi) genel görev listelerinden ayrılsın ve bir ajanın açtığı görev "ajan açtı" diye işaretlensin mi?" (channel message 6423), his own vote: "Evet, ikisi de; planını yaz", over "Yalnız ajan kanalının görevleri ayrılsın" and "Hayır, şimdilik kalsın".
- The option he chose said: an agent channel's tasks open apart with the "Agent channels" toggle on My Tasks, the Planning Board and the Work Calendar; a task an agent created is marked and can be filtered; one column on the task table for it (`decisions-made-schema-23.md`).
- No second concept: the agent channel switch of 2026-11-08 is widened (`workspace/agent-channel-flag.md`). Plan: `plans/agent-work-apart.md`.
