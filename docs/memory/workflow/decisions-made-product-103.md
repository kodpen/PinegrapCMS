---
name: decisions-made-product-103
description: Decisions made, product part 103 (2026-11-24 on): a subscription resumed after its charge date waits for the next series date; a held renewal with no outcome ends after 7 days
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-102.md`.

## What does resuming a paused subscription do when its charge date has passed? (owner, 2026-11-24, in the architect's window)
- It waits for the next series date: resume sets `next_charge_at` to the first series date after now, counted from `created_at` as the renewal does; nothing is charged on resume and the days between are free. His choice of three: "Sonraki seri gününe ertele".
- Not chosen: a charge on resume with a new series from that day (a column), and a charge on resume that skips the near series date.
- Why: the customer keeps the billing day signed up with and is never charged twice for overlapping time. Before, a resume charged at once and again a few days later (issue #11426, PR #11447).

## What happens to a held renewal when no outcome ever comes? (owner, 2026-11-24, in the architect's window)
- A renewal held for the customer's confirmation or for a pending or unclear charge ends after 7 days with no outcome, as the retry ladder's last step does: ended, the cancelled mail, the subscription webhook. The 7 days count from the hold's event in `subscription_events`.
- Stripe's `payment_intent.payment_failed` for a held renewal puts it on the 1/3/7 ladder at once. His choice of three: "7 gün sonra bitir + başarısız bildirimi oku".
- Not chosen: going back on the ladder after a wait, because a pending charge completed later and a retry could take the money twice.
- On a running site a renewal already held longer than 7 days ends on the first renewal run after the update. Before, it waited for ever (issue #11427, PR #11448).
