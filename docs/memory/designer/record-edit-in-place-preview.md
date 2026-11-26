---
name: record-edit-in-place-preview
description: "Edit content here" in the designer's Preview (RE3, #11677): the part recordedit, the switch, how frames find elements, the controls in place, the foot bar, a field changed meanwhile
module: designer
---

## Where is it built?
- Part `recordedit` (`includes/designer_parts/recordedit.php`): `pg_record_edit_spots.js` then `style_designer_recordedit.js`, loaded the first time a `preview_widgets` answer has a `form` setup with a record (`_sdRecEditSync()`). Core section `// ── Edit content here ──` (`_sdRecEdit*`) after "Preview as".
- The "Preview as" bar draws `K._sdRecEditHtml('bar')` last in its right end and `'row'` in the narrow sheet above "Reset"; each bar `render()` hands the document to `_sdRecEditSync()`.

## When is the switch shown?
- After `record_edit_read` of the item the first form widget draws answers `ok`. `waits: true`: the link "Open the record" (`edit_submitted_form.php?id=`, new tab) in its place. `missing`/`denied` (not a collection item, no right): nothing.
- Product, event: the link (`record-edit-in-place-canvas-row.md`).
- Off each time the Preview opens (`openPreview()` calls the part's `reset()`); another record drawn reads the new one.

## How do frames find the elements?
- On, `_sdRecEdit.marks` is true: `_sdPvAsApply()` sends `marks: true` and `preview_widgets` draws the widgets with `pg_designer_audit_render()` on (`data-pg-node`); `_sdPreviewDocHtml()` marks the page's own elements (`_sdRecEditMarkHtml()`, first tag of content, semantic and component nodes). Off, nothing is marked or sent.
- `PGRecordEditSpots.find(K.tree, fields, {formId: record.form_page_id, lookup})`; `lookup` reads `_sharedCache` (`regionType`, `custom_form_page_id` or `form_page_id`; a widget known only by `source_page` matches no form). Each spot's elements are `[data-pg-node="<node>"]`.
- A value of the page's record outside a widget draws as `[Label]` in the Preview; a frame writes the item's value into it.

## How is each field edited?
- Text and number: `contenteditable="plaintext-only"`, Enter or a click outside keeps, Escape gives back (a refused value goes back to the record's). A plain text area (`multiline`) keeps its line breaks. Formatted text bound to `html`: the small bar (Bold, Italic, Link, two lists, `execCommand`). Date: a calendar of the part, stored `YYYY-MM-DD`, shown with `toLocaleDateString()` in the page's language.
- Picture: "Change picture" opens "Choose a picture…" (`pgEditorPicker.pictures()` in the designer's window, a file with an id only), "Alternative text" when `alt` is bound to a field, "Remove the picture" when not required.
- A field drawn twice changes in both places as it is typed. Required empty or a number that is none: red frame with the reason, not counted.

## What does the foot bar do?
- `record_edit_save` with every counted change (`base` the value read, or theirs after "Keep mine"). `saved`: the changes sent are taken off (one typed meanwhile stays), the note line (`previewas.note()`), the Preview drawn again from the server (`_sdRecEditRedraw()` clears `_sdLive.sig`). `invalid`/`refused`: red frames and "1 of 2 changes was refused…". `conflict`: one card pair per field, the save goes on after the last. `approval`, `missing`, `denied`: the line says so and the switch leaves.
- A bar choice with changes not saved waits (`_sdPvAsChoose()` asks `holds()`): amber "Save your 2 changes … first?". Turning the switch off asks the same. Closing the window asks through the browser's `beforeunload`.
- A design edit reloading the Preview keeps the changes and the field being edited.

## What was measured?
- Marking 1,539 nodes: about 1 ms per Preview markup, only while on. Part: 31 KB minified, not in the core.

## Which test guards it?
- `tests/record_edit_preview_test.php`; `marks` in `tests/preview_as_bar_test.php`; `form_page_id` in `tests/record_edit_test.php`.

Related: docs/memory/designer/record-edit-in-place-server.md, docs/memory/designer/record-edit-in-place-spots.md, docs/memory/designer/preview-as-bar.md
