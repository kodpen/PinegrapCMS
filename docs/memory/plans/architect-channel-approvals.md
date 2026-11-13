---
name: architect-channel-approvals
description: Plan (2026-11-13): no architect issues any more; the merge tool's approval gate reads a decision comment on the PR itself, and the memory files that still say "open an architect issue" are corrected
---

## What is asked?
- Owner (2026-11-13): "artık mimar için issue açmasak daha iyi değil mi kanaldan kullanmaya devam ederiz". Questions and decisions moved to the agent channel the same day (`workflow/architect.md`, `workflow/architect-asking.md`).
- One issue is left: the approval issue. `merge_train_approval_waiting()` (`tools/merge_train.php`) holds a PR whose body says `Approval: #<issue>` while that issue is open with no `Karar`/`Decision` comment saying Onaylıyorum/Approved. Dropped without a new gate, a PR lands before the owner's answer, as #8222 did.

## Which steps?
- AQ1 (S) The gate on the PR itself: a body line `Approval: channel` holds the PR until a comment on that PR opens with `Karar` or `Decision` and says Onaylıyorum, Onaylandı or Approved (and no "Değişiklik istiyorum"), the words `merge_train_approval_given()` reads today.
  - Only a comment of an account with write access counts (`author_association`). `Approval: #<issue>` keeps working for the PRs open at the time. `--list` and `--build` name the waiting PR as today. Unit tests beside the tool's own.
  - Then `workflow/architect-asking.md` drops the approval issue: the session writes `Approval: channel`, PR MERGE writes the owner's channel answer as that comment, quoting him.
- AQ2 (S) The sweep: the memory files that still tell a session to open, list or wait on an `architect` issue are corrected to the channel way: `architect-decision-record.md`, `architect-no-hand-over.md`, `coordinator.md`, `coordinator-context.md`, `merge-review.md`, `session-lifecycle.md`, `test-coordinator.md` under `workflow/`. This plan is deleted.

## What does every step keep?
- No PR that waits for the owner's approval lands without it; the owner is never asked on GitHub; no decision already on the decisions list changes.
- The gate changes `tools/` only (no changelog line, no translation); the sweep is documentation only and may run beside it. Claim as `[plan:architect-channel-approvals] <step> — <topic>`.
