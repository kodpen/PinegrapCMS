---
name: bug-hunt-areas-record-edit-in-place
description: Bug hunt of record edit in place ("Edit content here", "Edit this record's content", 2026-11-26): what was fixed, what read clean, what was left
module: record_edit
---

## Which area and files?
- Record edit in place, given to K1's worker on 2026-11-26: `includes/fn/record_edit.php`, `includes/panel/record_edit.php`, its two actions in `includes/panel/actions.php`, the part `recordedit` (`assets/js/style_designer_recordedit.js`). Hunted in the PR `[iş] Bug hunt — record edit in place`.

## What was fixed?
- A formatted text was read raw into the Preview's document: a handler stored in it (`<img onerror>`) ran when "Edit content here" drew or opened it, and `{path}` showed as a broken address. The read now gives `pg_rec_edit_rich_out()` (cleaned as staff text, `{path}` filled), and the save cleans the sent value the same way before it compares and writes.
- A record whose collection sits in the Recycle Bin, and a picture file in the bin, were written: `pg_rec_edit_record()` answers none (`missing`) and `pg_rec_edit_picture_refusal()` refuses a binned file (`pg_folder_binned()`).
- No length was checked: a line of text or a number is held to the field's own Maximum Length, any value to `PG_REC_EDIT_MAX_LENGTH` (200,000 characters); a required formatted text holding tags only counts as empty (`pg_rec_edit_value_refusal()`).
- Two saves of one item at once both passed the changed-meanwhile check and the second wrote over the first: the read, check and write now run under `GET_LOCK('pg_rec_edit_<id>')`; a save that waits over 10 s is refused with "Someone else is saving this item."
- Typing in a plain text area dropped all its line breaks: the read names it `multiline` and the part keeps them.
- A change typed while a save was on its way was dropped by the answer: the part takes off only what it sent (`R.sent`).

## What read clean?
- Rights: as Edit Submitted Form (role 2 or better, or Manage Forms with the form folder's edit access); a page's viewer without that gets `denied`. Approval: role 2 and 3 get `approval`. A field of another form, or one with no in-place control (product, reference, rows, choices), is refused. Product, event and a waiting change get only "Open the record".
- Text is stored as typed and escaped on output; error messages are made plain by `field_error_texts()`.
- CSRF: both actions have `token` and call `validate_token()`. A save sent twice: the second finds the value stored and answers `unchanged`. The foot bar counts the changes it sends.

## What was left?
- A field not bound on the page can be written by a hand-made request; it is a field the person may write on the item's own screen, so no right is passed.
- A picture is any image file in a folder the person may view (`check_view_access()`); whether only folders they may edit should count is a product choice, sent to the coordinator.
- The item's own screen takes no lock, so a save there at the same moment is not ordered with one from the Preview.
