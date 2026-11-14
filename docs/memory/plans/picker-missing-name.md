---
name: picker-missing-name
description: Plan (owner 2026-11-14): a page or file field never leads with an id; the picker field's missing state in words, and a census of fields outside the picker that still print an id
---

## What is asked?
- Owner (2026-11-14, agent channel message 2380, with a picture of Edit User's User Start Page reading "#294"): "bu ve bunun gibi sayfa seçici olan alanlarda sayfanın id si yerine sayfanın adı. dosyanın id si yerine dosyanın adı çıktı versin."

## What is there today?
- A picker field already prints the stored page's or file's name (`ui/page-form-page-fields.md`, `ui/picker-field.md`, `ui/picker-page-fields-screens.md`).
- The picture is the missing state: the stored page is gone (dev has no page 294), so there is no name. `pg_picker_field_view()` (`includes/fn/picker.php`) then leads with the id as `#294`, the small line "Stored value · page not found" (a file: "… file not found") and an amber note. A file keeps its last known name where the field has one.
- Not known: whether a field or a list cell outside the picker still prints a page's or file's id where the name is known. The list screens' quick edit keeps its own lists, which show names.

## Which steps?
- PN1 (S) The missing state in words: the main text is "Page not found" / "File not found" (a file's last known name where there is one), the id moves to the small line ("Stored value · #294"), the amber note stays. One change in `pg_picker_field_view()`, so every picker field gets it; its test first. Real pictures of the field (dark and light) in the agent channel before the PR goes ready; `ui/picker-field.md` and `ui/page-form-page-fields.md` corrected in the same PR.
- PN2 (S) Census, then the fixes it finds: panel fields, list cells and summaries that print a stored page's or file's id where the name can be read (grep the screens for the page and file id columns printed raw). Each found place shows the name, by the picker's own reading where it fits. Nothing found: the census's result is written into `ui/picker-overview.md` and the step ends.
- PN3 (S) Close-out: this plan deleted, the rule kept in `ui/picker-field.md`.

## What does every step keep?
- The stored value is posted unchanged, as today: a missing, archived or binned page stays until the person chooses another.
- The wording of the missing state is the architect's reading of the owner's words; he changes it by saying so.
- No table or column. Claim as `[plan:picker-missing-name] <step> — <topic>`.
