---
name: decisions-made-product-28
description: Decisions list, product part 28 (2026-11-04 afternoon on): a changed expense updates its active repeat; a duplicated campaign profile starts disabled
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Does a changed expense update its active repeat? (#6687)
- Owner (2026-11-04): yes. `erp_expense_save()` updates, in the same transaction, the active recurrence of the family `erp_expense_recurrence_of()` finds for the changed expense (so a correction on an expense the repeat wrote reaches the same repeat): supplier, tax number, category, description, amount, tax rate, tax_deductible; the frequency, day and end stay. The change form says the active repeat takes the new values; expenses already written stay as they are. No schema. Reason: a correction (a supplier typo, a new rent) is almost always meant for the coming ones too, and no screen edits a recurrence; a one-off figure is typed on the written expense before paying it. Refused: showing on the Repeat card what the next one writes without changing it; leaving it as is.

## Does a duplicated campaign profile start enabled? (#6706)
- Owner (2026-11-04): no. `duplicate_email_campaign_profile.php` stores the copy with `enabled = 0` whatever the original holds; the duplicate notice adds that the copy sends nothing until it is enabled. Existing profiles unchanged, no schema. Reason: the copy shares the original's action, subject and body, so an enabled copy mailed every customer twice on every trigger until one of the two was disabled; a copy is made to be edited first. Same shape as the duplicated calendar event, which starts unpublished (#5582).
