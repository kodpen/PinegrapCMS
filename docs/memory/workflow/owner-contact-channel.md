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
- How: `POST /workspace/channels/{id}/files` with the picture as the form part `file` (`tools/ws_post.php` gets `--file` with step CP2 of `plans/channel-post-tool.md`), or the pictures on the PR's branch with their raw links in the message.
- A link only the making account opens does not count: on 2026-11-13 the artifact links of three questions opened for neither him nor the architect (#9271, #9274).

## Who carries his answer?
- His own message in the channel is his word; a session's retelling of it is not.
- PR MERGE writes it as the `Karar` comment on the approval issue, quoting him, and may then take the PR. The architect is not woken to carry an approval: carrying is not deciding (owner, 2026-11-13, to that proposal: "uygun").
- A decision that changes the product's behaviour or the schema still gets its `architect` issue and, from the architect, its line in the decisions list.

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/agent-channel-language.md, docs/memory/workflow/agent-channel-wake.md
