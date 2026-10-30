---
name: workflow-table-import-notes
description: A note's table block into a workflow table (T6b, #3044): notes and tables offered, cells read (tags, marks, formulas), then the file import's confirmation and writes; the note never changes
---

## Where does a person do it?
- `workspace_wtables.php`: the list's "Table from a note" card makes a new table; a writable table with columns has "Rows from a note". One select, one option per table (`note_id:index`), posted as `wt_action=import_note`; then the same confirmation, write and report as a file (`workflow-table-import.md`).
- The read is kept like a file (`ws_wtable_import_keep(…, $note = true)`); `kept['note']` makes the confirmation say "note" and offer the name as written (no extension cut).

## Which notes and tables are offered?
- `ws_wtable_import_note_choices()`: own notes and notes shared by name, through `ws_notes_list()` (which applies access). Its rows hold only a 140-character `excerpt`, no `body`, so one query reads the whole `body` of the listed ids holding a `|`, the first 50 in the list's order; `ws_wtable_import_note_choices_from($notes, $bodies)` (tested) builds the choices from those. Notes reached only through a channel share are not listed. Label: note title — the `::: Title` above the table, else its header names.
- Before #PR (issue #3380) it read `$note['body']` from the list rows: no note was ever offered and the Tables screen printed two "Undefined array key" warnings per note.
- `ws_wtable_import_note_read()` reads the note whole and checks `ws_note_access()` (`''` → "That note could not be found."); the table index gone → "That table is no longer in the note." The note is never written.

## How is a note's table read?
- `ws_wtable_import_note_tables($body, $max)` follows `ws_render_body()`: a `|…|` row, the row of dashes (`ws_table_alignments()`) under it, every `|…|` row after; tables inside code fences are skipped; each row padded or cut to the drawn column count (≤ 12). `more` past `$max` rows.
- `ws_wtable_import_note_cell()`: `<@user:N>` → the person's name (else N, which a person column takes as an id), another `@` tag → empty, `<#kind:N>` → `kind:N` (what a link column takes), `[words](url)` → words, `**` `__` `~~` `==` `` ` `` `*` `_` around the whole cell dropped.
- `ws_wtable_import_note_rows()`: a `=` formula cell becomes its value from `ws_calc_grid()` with the note's names (`ws_calc_body_scope()`), empty on an error.
- Tests: `tests/workspace_wtable_import_test.php` (note tables, cells, rows).
