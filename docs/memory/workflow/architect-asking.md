---
name: architect-asking
description: How a session asks a decision question or the owner's approval of screenshots: search first, one message in the agent channel naming the architect, the PR back to draft, the session ends
---

## How is one asked?
- First search the decisions list (`DECISIONS.md`) and the plan for the question: on 2026-10-27 six questions were opened twice within an hour. A worker asks only its own step's question; a plan's other open questions are the architect's.
- One message per decision in the agent channel, in English, naming the architect: the question, the options in a line each (what each changes, for running sites too, its cost), the recommendation, the PR and the plan step, what it blocks. No session or process trace (Fixed rules 2). No `architect` issue is opened (`architect.md`).
- A session without a channel key writes the same text in its PR body under `## Decision question`; its coordinator carries it into the channel.
- Then `Decision: channel message <id>` (or the section) in the PR body, the PR back to draft and its `ready` label off unless the PR does not wait for the answer, and the session ends (`session-lifecycle.md`): nobody waits for a decision.
- The question reaches the owner in Turkish from the architect, never from the asking session (`agent-channel-language.md`).

## How is the owner's approval of a PR asked?
- A PR whose plan says the owner approves its screens before merge: once green, the pictures go straight into the agent channel (dark and light, 1440 and 390 px) with the PR and the step named; the owner looks and answers there (`owner-contact-channel.md`). Pictures kept on the PR's branch for it (`docs/memory/plans/<plan>/<step>/`) are removed before the PR goes ready.
- The session writes `Approval: channel` in the PR body, keeps `ready` and ends; no approval issue is opened. Without the line the PR lands before his answer (B10/B11 on 2026-11-01, #8222 on 2026-11-08).
- PR MERGE writes his channel answer as a comment on that PR, quoting him: a first line opening with `Karar` or `Decision`, and Onaylıyorum, Onaylandı or Approved (or "Değişiklik istiyorum" for a change). `tools/merge_train.php` leaves the PR out (`--list` and `--build` name it, "waits on the owner's approval in this PR") until such a comment exists from an account with write access (`author_association` OWNER, MEMBER or COLLABORATOR).
- A PR opened before 2026-11-13 with `Approval: #<issue>` still waits on that issue: the `Karar` comment goes there and the issue is closed on landing.

Related: docs/memory/workflow/architect.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/owner-contact-channel.md
