---
name: collection-rows
description: Start file of the collection "Record rows" field (Kind "Rows"): rows of a record as child records, decisions, file map, R1 to R19 with PRs, open items
module: collection_rows
---

## What is it?
- A text box of a collection form with the Kind "Record rows" (`rows`) names a child collection; the record's rows are records of it, added, removed and ordered on the record's own form. The field's value is the rows' reference codes in order; no new storage, no schema.
- The way round before it (a second collection pointing back with a reference and a number for the order) still works (`collection-lists.md`).

## What binds it?
- Architect (2026-11-16): the codes stand on the parent, since the order and the membership are one value the record's History already keeps and no schema is needed; the child needs no link or order field, which could clash with a field the site uses. The limit of 100 rows a field holds.
- Owner (2026-11-16): a record's rows are deleted with it, and the delete confirmations name how many go (`collection-rows-delete-history.md`).
- Architect (2026-11-16): "Rows of the shown record" takes the rows field as a typed name, as a URL parameter's is; a name that names no rows field leaves the list empty (`collection-rows-list.md`).
- A row stands in one record's list only: every writer refuses a code another list holds.

## Which file holds what?
- The kind, the spec, the writer and its order of writes, the canvas sample: `collection-rows-writer.md`.
- The editor on Add and Edit Submitted Form, "Open the row", "Row of": `collection-rows-editor.md`.
- The same editor on a designed site form: `collection-rows-site-form.md`.
- Delete with the record, History and Load: `collection-rows-delete-history.md`.
- The API, a Workspace change, Import Submitted Forms: `collection-rows-outside.md`.
- The list on the record page: `collection-rows-list.md`.
- Code: `includes/fn/collection_rows.php`, `assets/js/pg_collection_rows.js`; tests `tests/collections_rows*_test.php`.

## Which features were built, and by which PR?
- R1 to R7 (kind, value, spec, writer, foreign code, limit, incomplete record) and R15 (canvas sample): #9884.
- R8 to R10 (panel editor, one JSON field, "Open the row"): #9898.
- R11, R12 (delete, History): #10457.
- R13, R14, R19 (API, outside writes, import): #9899.
- R16 (site form editor, 256 KB bound): #10455.
- R17, R18 ("Rows of the shown record", "Rows of this record"): #9897.

## What is still open?
- R14 for the content import (WordPress importer, `includes/import/write_record.php`): its mapping offers any text box, a rows field too, for Title, Body, Summary, Date, Categories and Tags, and writes the text unchecked. A tag named like a child record's code would put that row on a second list. Not built; it needs the rows field left out of the mapping or `pg_coll_rows_outside_refusal()` on the write.
- No flow writes record values today; one that does goes through `pg_coll_rows_outside_refusal()`.
- No screen copies a record; one that does must make the rows anew.

## What is left out?
- Rows inside rows; a row's file upload on the parent's form (it opens the row's own screen); a row moved to another record; the rows' text in the parent's site search entry. Each is a later yes from the owner.

## Whose look?
- The rows editor on the record screens is the owner's; the Kind choice and the canvas sample are the architect's.
