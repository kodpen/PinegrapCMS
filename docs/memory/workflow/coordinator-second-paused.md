---
name: coordinator-second-paused
description: Since 2026-11-25 the second coordinator (k2) takes no new work and the design session is stopped, both for their account's quota: k1 gives out k2's open steps, the architect draws; the owner's words
---

## What did the owner say?
- Owner (2026-11-25, agent channel message 13298): "tasarımcı hesabı kotası az kaldı aynı hesabı paylaşan @k2 de öyle @tasarim durdu artık görevleri mimarın".
- "@k2 elindeki işleri bitirince yeni iş alma kalan işler açık adım olarak kalsın ve işi biten işçilerini arşivle. @k1 sen devam et."
- "prmerge devam mimar şimdilik devam ancak onunda hesap kotası sınırda o yüzden yakında başka hesaba taşımayı planlıyorum acil değil."

## What follows for the coordinators?
- k2 starts no worker. A worker of its that is running finishes, labels its PR `ready` and is archived; its steps that nobody holds stay open in their plan files.
- k1 gives out any plan's free step, k2's plans included (architect, 2026-11-25, agent channel request 2902), by `plan-priority.md` and each plan's own "after". A step a k2 worker still holds stays with that worker until its PR has landed.
- k1 still keeps 8 working inside the queue limit (`coordinator-workers.md`); plan steps come before a bug hunt.
- `coordinator.md`'s "one per account" stands for the accounts that run a coordinator.

## What follows for the drawings and the architect?
- The design session's sheets are the architect's: `design-work-session.md`.
- The architect session moves to another account when the owner does it; what its next session needs is in `architect-hand-over.md`.

## When does this end?
- When the owner says k2 or the design session runs again: this file is then corrected or deleted.
