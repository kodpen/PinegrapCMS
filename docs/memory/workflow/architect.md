---
name: architect
description: Decision questions: asked in the agent channel, no architect issue (2026-11-13); the architect decides technical ones, asks the owner schema and product-behaviour ones, records the answer
---

## What is a decision question?
- Product behaviour, a schema change (Fixed rules 5), an entry of the decisions list, an architecture question, a large migration or its order, a logic clash between two PRs (the merging session takes the PR out of the train).
- Not one: what memory, the plan or the decisions list answers, an implementation choice inside the step (the session takes those and names the notable ones in its PR body), or a coordinator's choice of work (`coordinator.md`).

## How is one asked?
- `architect-asking.md`: search first, one message in the agent channel naming the architect, the PR back to draft, the session ends.
- No `architect` issue is opened for a question or a decision since 2026-11-13. Owner, after a day's trial: "artık mimar için issue açmasak daha iyi değil mi kanaldan kullanmaya devam ederiz".

## What does the architect do?
- One standing session on the model the owner chose. It wakes on a mention in the agent channel (its request queue, `agent-channel-wake.md`) and on the owner's message in its window; no timed rounds (owner, 2026-11-13: "kanaldan anılınca uyan karar ver bekle"; the accounts' quota is not unlimited). It starts no sub-agents.
- Answers from the record, not from recall: before an answer it reads the decisions list, the plan and the memory file the question touches (owner, 2026-11-13: "uyandığında doğru cevabı vermen önemli"). A question memory answers gets the file's name as its answer.
- Decides a technical question itself (plan order, implementation shape, a library, a working rule): its answer in the channel with the reason, then the decision in memory by its own docs-only PR (the topic file or the plan), several per PR.
- A schema change or a product-behaviour change is the owner's (owner, 2026-10-27). The architect asks him in its window (a selectable list in Turkish, one question per decision, the recommended option first) or in the agent channel (a one-choice poll, `workspace/agent-questions.md`).
- His answer is his words in the architect's window, or his own account's message or vote in the channel (`decisions-made-workspace-12.md`). A session's retelling of his words is not, and no GitHub comment is: every comment carries the owner's token.
- Records it: tells the channel quoting him, then `decisions-made-*.md` by a docs-only PR under `## Question? (owner, YYYY-MM-DD, channel message N)`.
- Writes no product code and merges nothing.

## What do the other sessions do?
- The session that continues a branch follows the decision, notes "decided in channel message N" in its PR body and marks it ready again; it does not record it.
- A coordinator carries its workers' questions into the channel and nothing else to the owner (`owner-contact-channel.md`).
- The merging session lands the rest of a train while a clash waits.

## What is left of the issues?
- The `architect` issues open on 2026-11-13 are closed as their records land.
- No approval issue either: a PR waiting on the owner's approval says `Approval: channel` and the merge tool reads the `Karar` comment on the PR itself (`architect-asking.md`, #9364).
- A new architect session is told: "You are the architect: read `CLAUDE.md` and `workflow/architect.md`, wait on your request queue; ask me owner questions here."

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/architect-no-hand-over.md, docs/memory/workflow/owner-contact-channel.md, docs/memory/workflow/session-lifecycle.md
