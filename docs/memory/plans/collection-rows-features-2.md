---
name: collection-rows-features-2
description: Plan collection-rows, features R13 to R19: the API, the other writers of a record and the import, the rows control on a site form, the list on the record page
---

## What do the API and the other writers do?
- (R13) A single record's read gives `rows` for a rows field: each row's id, reference code and values, in order; a list read gives the codes. `GET /forms/{id}/fields` names the kind and the child form.
- (R14) Wherever a record's values are written from outside the rows editor (the API, a flow, a Workspace change), a rows field takes only codes of the child form's records that stand in no other record's list; anything else is refused, 422 on the API.
- (R19) Import Submitted Forms writes no rows field: the cell is skipped and the import says so, so no record takes over another's rows; the export writes the codes. No screen copies a record today; one that does must make the rows anew.

## What does the site show?
- (R15) A rows control draws nothing on a site form until RW5 and three sample lines on the canvas (PHP and JS twins).
- (R16) From RW5 a form a visitor or member fills in draws the same editor in the site's markup; the rows are written by the same writer after the record is saved, within a bound on the request's size.
- (R17) A list's dynamic value "Rows of the shown record" (with the rows field picked) lists the child records whose code the shown record's field holds, in the field's order; server and editor offer the same values (the twin test).
- (R18) On a record page the designer's "Rows of this record" adds a list already set up that way.
