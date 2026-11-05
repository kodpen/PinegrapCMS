---
name: decisions-made-product-28
description: Decisions list, product part 28 (2026-11-04 afternoon on): expense repeats, duplicated campaign profiles, the new logo, the image editor's selection bar and Cut to a layer
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Does a changed expense update its active repeat? (#6687)
- Owner (2026-11-04): yes. `erp_expense_save()` updates, in the same transaction, the active recurrence of the family `erp_expense_recurrence_of()` finds for the changed expense (so a correction on an expense the repeat wrote reaches the same repeat): supplier, tax number, category, description, amount, tax rate, tax_deductible; the frequency, day and end stay. The change form says the active repeat takes the new values; expenses already written stay as they are. No schema. Reason: a correction (a supplier typo, a new rent) is almost always meant for the coming ones too, and no screen edits a recurrence; a one-off figure is typed on the written expense before paying it. Refused: showing on the Repeat card what the next one writes without changing it; leaving it as is.

## Does a duplicated campaign profile start enabled? (#6706)
- Owner (2026-11-04): no. `duplicate_email_campaign_profile.php` stores the copy with `enabled = 0` whatever the original holds; the duplicate notice adds that the copy sends nothing until it is enabled. Existing profiles unchanged, no schema. Reason: the copy shares the original's action, subject and body, so an enabled copy mailed every customer twice on every trigger until one of the two was disabled; a copy is made to be edited first. Same shape as the duplicated calendar event, which starts unpublished (#5582).

## A selection bar in the image editor on a phone (owner, 2026-11-04)
- Where the panel is a sheet, the selected layers, picture or area get their main commands in a bar over the stage (Options, Duplicate, Delete; Lock; the area's commands). Why: on a phone a selected picture could not be deleted and the background tools after a lasso were out of reach. Details: `ui/image-editor-actions.md`.

## The new logo everywhere (owner, 2026-11-04)
- The squirrel in its space window and the single-line Pinegrap wordmark replace every old logo. A narrow place shows the square mascot, a wide place the full logo, whatever it showed before. The panel's own screens (header, loading curtain, installer, theme designer) play the mascot's loop, with the still frame for reduced motion, a data saver, a small device or a refused play; icons and files are still. The SVGs of the mascot, the wordmark and both together stay in `assets/images`. The previous image editor's logo goes (`clean_up.php`). Nothing is drawn over the logo: the version pill under the mascot went the same day; the version stays in the page title. Why: one recognisable brand, and the very old "PineGrap™" icons kept showing. Details: `ui/brand-logo.md`.

## Cut to a layer in the image editor (owner, 2026-11-05)
- After Select area, Cut to a layer makes the selection its own see-through picture layer and erases the same outline from the picture, in one undo step; Erase inside and Keep only stay as they were. The uncut picture must stay restorable: undo, deleting the erased area, and the saved versions bring it back. Why: the kept part should behave as a separate PNG piece. Details: `ui/image-editor-area-cut.md`.
