---
name: mass-edit-find-replace
description: The Find & Replace card of mass_edit.php: its liveform fields, what a refused apply comes back with, the first-draw defaults
---

## How is the card drawn?
- A separate form in `mass_edit.php` (`action=find_replace`) on the same liveform as the main mass-edit form (`mass_edit`). Find, Replace with, Regex and the three `fr_fields[]` boxes (Page Name, Web Browser Title, Web Browser Description) are `$liveform->output_field()` fields; Find is `required`.
- Title and Description are ticked only while `prefill()` is true (`$fr_defaults`), i.e. on a first draw. Page Name has no default.

## What does a refused apply keep?
- The refusals are an empty Find, no box ticked, an invalid regex. After them, when `check_form_errors()` is true, the branch calls `add_fields_to_session(array('trim' => false))` before the redirect (untrimmed: spaces count in a pattern). The screen comes back with the text, Regex and exactly the boxes ticked.
- Before, the card was hand-written HTML with no values: Find and Replace came back blank, Regex off and the boxes back to Title and Description, so a user who had ticked only Page Name could re-apply the permanent replace on fields they had unticked.
- A successful apply keeps nothing: the screen's `remove_form('mass_edit')` after drawing clears the session copy either way. No query was added (`db_q_pages_regions_test.php` counts them).
- Test: `tests/mass_edit_find_replace_keep_test.php`.

Related: docs/memory/ui/page-form-refused-save.md, docs/memory/ui/liveform-notices-trap.md
