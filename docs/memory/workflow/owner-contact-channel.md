---
name: owner-contact-channel
description: Where sessions reach the owner (2026-11-13): no coordinator writes to him in its chat window or waits there; problems to PR MERGE in the agent channel; visuals posted there, his answer there
---

## Who talks to the owner, and where?
- Owner (2026-11-13), in the architect's window: "koordinatörler hala benimle kendi sohbet penceresinden konuşmaya çalışıyor ve iş almadan bekliyorlar. bunun önüne geçilebilir, sorun olmadıkça sohbet penceresinden yazmasınlar sorun olduğunda pr merge kanaldan bildirsinler o ihtiyaç duyarsa seni uyandırsın. mimari kararlar sende, tasarlanan görseller kanala direkt konsun. ben oradan kontrol edeyim."
- A coordinator does not write to the owner in its own chat window and does not wait there for him. With no problem it takes the next free step by itself (`php tools/free_work.php`; the choice of work is its own call, Working mode 10), under the queue limit.
- A problem it cannot settle: one English message in the agent channel naming PR MERGE. PR MERGE settles it, or names the architect, who wakes on a mention only (no timed rounds since 2026-11-13, to spare the quota).
- Architecture decisions are the architect's. A product or schema question still reaches the owner only through the architect (`architect-asking.md`).

## Where do visuals go?
- Drawings and screenshots that wait for his eye are posted straight into the agent channel, with the PR and the step named; he looks and answers there.
- How: `php tools/ws_post.php --file=<path> [--parent=<id>] "<PR, step, what to look at>"` (`POST /workspace/channels/{id}/files`, form part `file`; prints the message id). One file a call: the first screenshot carries the text, the rest answer it with `--parent=<its id>`. A refusal (size, kind) prints the API's message and exits 1; the key rule of [[agent-channel-post-repeat]] holds (#9377).
- A link only the making account opens does not count: on 2026-11-13 the artifact links of three questions opened for neither him nor the architect (#9271, #9274).

## Who looks at a screen's pictures? (owner, 2026-11-14, channel message 2645)
- To the architect's five proposals (channel message 2639) he answered: "5 önerin kabul. Onaylıyorum".
- A screen whose behaviour its plan has decided: the architect looks, at one thing, whether it follows look A and the design sheet (`ui/list-table-layout.md`, `plans/panel-design-language/`). Its channel message saying so lets the PR land; the `Karar` comment quotes that message and names 2645. It is written as the architect's look, never as one the owner gave.
- A new design that no plan or sheet holds, and every product behaviour question, stay his.
- A screen step uses the sheet's component first and posts its whole set at once (dark and light, 1440 and 390 px).
- PR MERGE keeps one message "waiting for the owner" current in the channel: every PR and question that waits on him, a line each.
## Who carries his answer?
- His own message in the channel is his word; a session's retelling of it is not.
- PR MERGE writes it as the `Karar` comment on the PR itself (`Approval: channel`, `architect-asking.md`), quoting him, and may then take the PR. The architect is not woken to carry an approval: carrying is not deciding (owner, 2026-11-13, to that proposal: "uygun").
- A decision that changes the product's behaviour or the schema still gets, from the architect, its line in the decisions list (`architect.md`).

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/agent-channel-language.md, docs/memory/workflow/agent-channel-wake.md
