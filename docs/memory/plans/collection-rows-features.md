---
name: collection-rows-features
description: Plan collection-rows, features R1 to R18: the kind and the field's value, the writer, the panel editor, delete and History, the API, the site form, the list on the record page
---

## What is the kind, and what does the field keep?
- (R1) A field of a form used as a collection can have the kind `rows`, on a text box: it names one child collection, never its own form and never a form that is not a collection. Offered as "Rows" in the Form Field panel's Kind with the collection picker.
- (R2) The field's value is the reference codes of its rows in order, joined as a reference in a text box joins several codes. A row is a record of the child collection that the rows editor made; it stands in one record's list only. The child needs no field for it.
- (R3) `pg_coll_rows_spec()` (pure) answers, for a rows field, the child form and the child's fields the editor takes, or why nothing can be drawn (no collection, gone, no field it takes).

## How is a posted list written?
- (R4) Every row is checked first with the child's own field checks; one refused row refuses the whole save, with its line and field marked.
- (R5) Then, in this order: new rows created, changed rows written as the edit screen writes a record, the parent's value set to the codes in order, removed rows deleted. A failure midway never leaves the parent listing a row that does not exist.
- (R6) A code that is not in the parent's stored list is refused: a row of another record is never taken over. At most 100 rows a field.
- (R7) Rows of a record that is not complete are kept as it is; a row is not asked to be complete by itself.

## What does the editor on the panel's record screens do?
- (R8) On the add and edit record screens a rows field draws a plain table: a column per child field of type text box, text area, pick list, check box, date or time; "Add row", Remove, a drag handle and Move up and Move down buttons.
- (R9) The list travels as one JSON field the script builds, never as an input per cell: PHP's `max_input_vars` would cut a long list in silence.
- (R10) A child field the table does not take (a file, rich text) shows "Open the row" once the row exists: the row's own record screen in a new tab, which names its parent with a link back.

## What happens on delete and in History?
- (R11) Deleting a record for good deletes its rows; the delete confirmations name how many rows go with it. (After the owner's answer.)
- (R12) The parent's History shows a change of its rows as a change of that field. Loading an older version keeps only the rows that still exist and says how many could not be brought back. A row's own changes are in the row's History.

## What does the API give?
- (R13) A single record's read gives `rows` for a rows field: each row's id, reference code and values, in order; a list read gives the codes. `GET /forms/{id}/fields` names the kind and the child form.
- (R14) A write of the field takes only codes of the child form's records; anything else is 422.

## What does the site show?
- (R15) A rows control draws nothing on a site form until RW5 and three sample lines on the canvas (PHP and JS twins).
- (R16) From RW5 a form a visitor or member fills in draws the same editor in the site's markup; the rows are written by the same writer after the record is saved, within a bound on the request's size.
- (R17) A list's dynamic value "Rows of the shown record" (with the rows field picked) lists the child records whose code the shown record's field holds, in the field's order; server and editor offer the same values (the twin test).
- (R18) On a record page the designer's "Rows of this record" adds a list already set up that way.
