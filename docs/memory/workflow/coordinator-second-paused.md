---
name: coordinator-second-paused
description: Coordinators k1, k2, k3: k2 stopped 2026-11-25 for its quota and runs again on what is left; k3 takes k1's line from 2026-11-26; which plans each gives out; the design session stays stopped
---

## What did the owner say?
- Owner (2026-11-25, agent channel message 13298): "tasarımcı hesabı kotası az kaldı aynı hesabı paylaşan @k2 de öyle @tasarim durdu artık görevleri mimarın".
- "@k2 elindeki işleri bitirince yeni iş alma kalan işler açık adım olarak kalsın ve işi biten işçilerini arşivle. @k1 sen devam et."
- "prmerge devam mimar şimdilik devam ancak onunda hesap kotası sınırda o yüzden yakında başka hesaba taşımayı planlıyorum acil değil."

## What follows for the coordinators?
- Until the evening of 2026-11-25 k2 started no worker and k1 gave out every plan's free step (architect, agent channel request 2902).
- Since 2026-11-25 19:59 UTC k2 runs again: by its own message 13690 in the agent channel the owner restarted it on the account's remaining weekly quota, about 16%, which resets on Friday 2026-11-27 at 14:00 UTC. It keeps a few workers at a time, as many as that quota carries, not 8.
- Architect (2026-11-25, agent channel request 2975): the two give out work by area, so their workers do not meet in the same files. k1 keeps the `designer-*` plans, `legacy-editable-steps` and `workspace-channel-board`.
- k2 takes `parasut-devhub-connection`, `security-scan` (SC9), `agent-memory-search` (opened by the owner on 2026-11-26, agent channel message 14119), and `claude-plugin-bundle` when its own plan text frees a step (it waits for the launch).
- A plan neither list names goes to the coordinator that says so first in the agent channel; a designer plan is always k1's. Open `bug` issues stay work for both.
- `coordinator.md`'s "one per account" stands for the accounts that run a coordinator.

## What follows for the drawings and the architect?
- The design session's sheets are the architect's: `design-work-session.md`.
- The architect session moves to another account when the owner does it; what its next session needs is in `architect-hand-over.md`.

## What changed with the third coordinator?
- Owner (2026-11-26, agent channel message 14049): "@k1 k1 sen elindeki işler bitince işler bitince yeni iş alma. devir yapacağım." He then brought k3 (Koordinatör 3) into the channel (message 14054).
- Architect (2026-11-26, agent channel requests 3088 and 3097): k1 gets no new task or hunt area; its workers finish what they hold. k3 takes k1's line as it empties: the `designer-*` plans, `legacy-editable-steps`, `workspace-channel-board`, the Office plan's last step and the steps k1 had waiting. k2's list is unchanged; where the lines above say k1, read k3 for new work.
- While k1's PRs are open they hold their files: k3 reads the open PRs and their files before each task, and a step that is "after X" starts when X has landed.

## When does this end?
- The split by area ends when k2 stops again (its quota) or the owner changes it: this file is then corrected. The design session's part ends when the owner starts one again.
