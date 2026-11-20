---
name: office-own-code
description: Plan (owner 2026-11-19, poll 156 "Tam kapsam"): the own Office code grows after PHPExcel goes: more previews, wider editing, shared Excel export and import; order and rules
---

## What did the owner ask?
- Owner (2026-11-19, agent channel message 9751): "kaliteli bir planla tam geçişi yapalım ve yazılım geneli kullanım alanını genişletelim. Dosya yönetici ve kanallarda önizleme ve düzenleme açısından özellik genişlemesi yapalım. Kaliteli ve modern bir plan çıkart yaptır kurtulalım eski paketten".
- Scope, poll 156 (message 9752): "Tam kapsam". Record: `workflow/decisions-made-product-79.md`.

## What are the parts?
- The move off PHPExcel: `plans/phpexcel-own-code.md`, XL1 to XL5. It comes first and stands alone.
- The growth, in `plans/office-own-code-steps.md`: previews OP1 to OP4, sheet editing OE1 to OE5, document editing OD1 to OD3, software-wide OS1 and OS2.

## In which order?
- XL1 first: its sheet reader is what OP2, OP3 and OS2 read through. XL2 to XL5 follow as that plan says.
- Line "window" (the file window and a channel's preview: `includes/fn/office.php`, `includes/fn/office_edit.php`, `pg_office_view.js`): OP1, OP2, OP3, OP4, OE1 to OE5, OD1 to OD3, one step at a time, each after the one before it has landed, as they share these files (`workflow/coordinator-worker-steps.md`).
- Line "lists" (panel lists and importers): OS1, then OS2 after XL1 to XL3. It may run beside the window line.
- One PR a step, about 1,000 product lines; a step that grows past it is cut by its worker into a and b and says so in the plan.

## Which rules does every step keep?
- No library is added and none is loaded from a CDN: readers and writers are the product's own (`platform/office-files-scope.md`).
- No schema. A step that finds it needs a table or column stops and asks the architect.
- An edit writes only what it changes: every other part of the file is carried over as it is (`platform/office-cell-edits.md`, `platform/office-paragraph-edits.md`); a file an edit cannot carry safely is refused with its reason, never written damaged.
- Formulas are never computed: a formula is kept, shown with its last stored result, and loses that result when a cell it may depend on changes, as today.
- The preview's limits and refusals (`platform/office-files.md`, `platform/office-dom-ceiling.md`), the cell locks (`platform/office-cell-locks.md`) and the upload refusals of macro forms stay; a new format gets the same limits.
- Who may edit is who may edit the file today; no new permission.
- Screens follow look A and the panel's button rules (`ui/`), every text goes through `lang()` with its Turkish, and the same controls serve the file window and a channel's preview.
- Tests: each reader and writer step has unit tests on fixture files built in the test itself; a written file is read back by the product's reader and its untouched parts compared byte for byte.

## What is not built?
- A formula engine, charts, pivot tables, tracked changes, comments, page layout, an editor that keeps a document's whole layout (needs a document server), .doc and .ppt binary files, editing of .xls, .ods, .csv or .pptx in place (OP3 offers a copy as .xlsx).

## How does the plan close?
- `platform/office-files-scope.md` and `platform/office-files.md` say the new state, each step's facts are in its topic file under `platform/`, a `platform/office-overview.md` lists them, and both plan files are deleted.
