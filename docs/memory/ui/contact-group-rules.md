---
name: contact-group-rules
description: Rule editor of a contact group filled by rules (Edit Contact Group, pg_audience_editor.js): switch, rows, pickers, live count, Refresh now, the save, list columns, contact card chips
module: audiences
---

## Where is it?
- Edit Contact Group (`edit_contact_group.php`) draws `pg_contact_group_rules_card()` (`includes/forms/contact_group_form.php`) in the form column after the shared cards once `pg_audience_ready()`. Create Contact Group has no card: a group is made first, then given rules.
- Managers and above (role < 3) edit rules; the user role sees a read-only line on a group filled by rules and the save ignores `kind` and `rules` from it. Rules read orders, consents and visits, which the user role does not see elsewhere.

## How does the editor work?
- `assets/js/pg_audience_editor.js` (served as written, no bundle) reads its config from `data-pg-audience`: `pg_audience_editor_data()` (conditions with family, value kind, operators and labels; fields, consents, statuses, the per-level cap), the stored rules, the names of the records they pick (`pg_audience_ref_labels()`), the texts.
- It keeps the hidden `rules` field as the JSON of `pg_audience_rules_clean()`; the server cleans it again on save. One "group of conditions" level under the top one, each level all/any. Changing a condition or operator redraws only that row.
- Money is typed in whole units with cents and stored in minor units (x 100). Product, product group, contact group, form and form field are `data-pg-combobox` selects searching `edit_contact_group.php?request=pick&kind=…&q=` (`pg_audience_picker_search()`, role < 3, JSON).
- The count line asks `api.php` `audience_count` (any_role row, handler refuses role 3, token) 600 ms after the last change: `pg_audience_count()` and `pg_audience_count_text()` (exact under 100, then "about" rounded to 10, 100 or 1,000).
- Refresh now (only on a group already saved as dynamic) calls `audience_refresh` until `done` (`core/audiences-recompute.md`); it uses the saved rules. The button is disabled only while a request runs.

## What does a save write?
- `pg_audience_editor_posted()`: `kind` is `dynamic` only with the switch on; the cleaned rules are stored either way, so switching back to static keeps them. Dynamic with no condition is refused with a message.
- Turning a group dynamic or changing its rules (`pg_audience_rules_changed()`, compared cleaned) sets `computed_at = 0`: the next general job tick takes it. A pass under way keeps its cursor.
- Switch review: on a static group with hand, form or product rows, the card shows inside the switch's collapse the counts ("1,240 by hand, 30 from forms") and a `switch_rows` radio: Keep them (default), Remove them, Let the rules decide them (`to_rule`). Only the save that turns a static group dynamic runs the choice (`pg_audience_switch_action()`, `pg_audience_switch_apply()`, `core/audiences-doors.md`); a group already dynamic shows no review.
- Delete of a dynamic group is held back only by rows that are not `source = 'rule'`; the delete also removes the recompute's state file.

## What do the list and the contact card show?
- All Contact Groups: a Members column (By rule / By hand) and the member count (one GROUP BY over the xref).
- The contact card (`pg_contact_form_group_cells()`): a dynamic group has no switch; when the contact is in it, it shows under "Members by rule" as a chip linking to the group. `pg_contact_form_all_groups()` leaves dynamic groups out, so the contact save neither adds to them nor removes an unticked contact, and the user role's "keep one group" check counts only static ones.

## Tests?
- `tests/audiences_switch_review_test.php`: the posted choice, the count line, the card and the save name them.
- `tests/audiences_editor_test.php`: the catalogue matches `pg_audience_conditions()`, refs by kind with nesting, the posted kind and rules, rules compared cleaned, the count text.

Related: docs/memory/core/audiences-rules.md, docs/memory/core/audiences-recompute.md
