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
- One standing session on the model the owner chose. It wakes on a mention in the agent channel (`architect-wake.md`) and on the owner's message in its window; no timed rounds (owner, 2026-11-13: "kanaldan anılınca uyan karar ver bekle"). It starts no sub-agents.
- Answers from the record, not from recall: before an answer it reads the decisions list, the plan and the memory file the question touches (owner, 2026-11-13: "uyandığında doğru cevabı vermen önemli"). A question memory answers gets the file's name as its answer.
- Decides a technical question itself (plan order, implementation shape, a library, a working rule): its answer in the channel with the reason, then the decision in memory by its own docs-only PR (the topic file or the plan), several per PR.
- A schema change or a product-behaviour change is the owner's (owner, 2026-10-27). The architect asks him in the agent channel only: a one-choice poll, one question per decision, the recommended option first (`workspace/agent-questions.md`).
- Never in its own window (owner, 2026-11-25: "sorular kanaldan buradan soru sorarsan göremem, kaçırırız. onlarca ajana bakıyorum"); no turn ends on a question written there.
- His answer is his words in the architect's window, or his own account's message or vote in the channel (`decisions-made-workspace-12.md`). A session's retelling of his words is not, and no GitHub comment is: every comment carries the owner's token.
- Records it: tells the channel quoting him, then `decisions-made-*.md` by a docs-only PR under `## Question? (owner, YYYY-MM-DD, channel message N)`.
- Keeps both coordinators' places supplied without being asked: old panel screens moved to look A, bug hunts in named areas (`bug-hunt.md`), above all a new feature (owner, 2026-11-15: "mimar olarak 2 koordinatöre yapacak yeni işler sunmanı bekliyorum", "işçiler saatlerdir boşta bekledikleri için geliştirme yine durmuş").
- Writes no product code and merges nothing.

## What do the other sessions do?
- The session that continues a branch follows the decision, notes "decided in channel message N" in its PR body and marks it ready again; it does not record it.
- A coordinator carries its workers' questions into the channel and nothing else to the owner (`owner-contact-channel.md`).
- The merging session lands the rest of a train while a clash waits.

## What is left of the issues?
- No approval issue: a PR waiting on the owner's approval says `Approval: channel` and the merge tool reads the `Karar` comment on the PR itself (`architect-asking.md`, #9364).
- A new architect session is told: "You are the architect: read `CLAUDE.md` and `workflow/architect.md`, wait on your request queue; ask me owner questions in the agent channel."

Related: docs/memory/workflow/architect-asking.md, docs/memory/workflow/architect-open-prs.md, docs/memory/workflow/architect-no-hand-over.md, docs/memory/workflow/owner-contact-channel.md, docs/memory/workflow/session-lifecycle.md
