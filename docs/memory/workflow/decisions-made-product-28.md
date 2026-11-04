---
name: decisions-made-product-28
description: Decisions list, product part 28 (2026-11-04 afternoon on): a changed expense updates its active repeat; a duplicated profile starts disabled; the new logo; the API does not delete store credit
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Does a changed expense update its active repeat? (#6687)
- Owner (2026-11-04): yes. `erp_expense_save()` updates, in the same transaction, the active recurrence of the family `erp_expense_recurrence_of()` finds for the changed expense (so a correction on an expense the repeat wrote reaches the same repeat): supplier, tax number, category, description, amount, tax rate, tax_deductible; the frequency, day and end stay. The change form says the active repeat takes the new values; expenses already written stay as they are. No schema. Reason: a correction (a supplier typo, a new rent) is almost always meant for the coming ones too, and no screen edits a recurrence; a one-off figure is typed on the written expense before paying it. Refused: showing on the Repeat card what the next one writes without changing it; leaving it as is.

## Does a duplicated campaign profile start enabled? (#6706)
- Owner (2026-11-04): no. `duplicate_email_campaign_profile.php` stores the copy with `enabled = 0` whatever the original holds; the duplicate notice adds that the copy sends nothing until it is enabled. Existing profiles unchanged, no schema. Reason: the copy shares the original's action, subject and body, so an enabled copy mailed every customer twice on every trigger until one of the two was disabled; a copy is made to be edited first. Same shape as the duplicated calendar event, which starts unpublished (#5582).

## The new logo everywhere (owner, 2026-11-04)
- The squirrel in its space window and the single-line Pinegrap wordmark replace every old logo. A narrow place shows the square mascot, a wide place the full logo, whatever it showed before. The panel's own screens (header, loading curtain, installer, theme designer) play the mascot's loop, with the still frame for reduced motion, a data saver, a small device or a refused play; icons and files are still. The SVGs of the mascot, the wordmark and both together stay in `assets/images`. The previous image editor's logo goes (`clean_up.php`). Nothing is drawn over the logo: the version pill under the mascot went the same day; the version stays in the page title. Why: one recognisable brand, and the very old "PineGrap™" icons kept showing. Details: `ui/brand-logo.md`.

## Does the external API delete a store credit card? (#6783)
- Decided (architect, 2026-11-04, the owner's #6688 applied to the API door): no. `api_gift_cards_delete()` refuses a card with `gift_cards.user_id` > 0 with 409 `store_credit_not_deletable` and a message naming the void route (`POST /customers/{id}/credit/{card_id}/void`, `api/api-customer-credit.md`), which already writes the note and the audit line; ordinary gift cards are deleted as before. No new void operation: the route exists. Reason: the owner's reason (the credit vanished with no audit line; the redemption history, the customer's list and the audit link broke) is about the row, not the screen, and the resource's own comment promises the screen's behaviour. Refused: leaving the API delete as it is.
