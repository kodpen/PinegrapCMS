---
name: record-edit-in-place-canvas-row
description: The canvas row "Edit this record's content" / "Open the record" (RE4, #11690): where it shows, preview_record_open, a click, the bar's link for a product or an event
module: record_edit
---

## Where is the row?
- In the canvas element menu (`showCanvasCtxMenu()`, after "Duplicate", also in view mode: it changes the record, not the page) and as the command palette row `act_record_edit` while one element is selected. No bar button (`designer-light-and-simple.md`).
- Shown for one element whose `text`, `html`, `src` or `alt` is bound and that sits in a record widget's tree (form, product or event view), or that binds `__record__…` on a page whose first record widget is one (`_sdRecWidgetOf()`).

## What decides its words?
- The designer sub action `preview_record_open` (`includes/panel/designer.php`, content roles allowed, page cache clean) runs `pg_rec_edit_open()` (`includes/fn/record_edit.php`): the record the widget shows in the Preview (the one kept in "Preview as", else the default pick) and the rights of its own screen.
- `pg_rec_edit_open_out()` (pure): a collection item the person may edit and whose change does not wait gives `edit: true`, "Edit this record's content"; a waiting change, a product or an event gives "Open the record" with `screen` (`pg_rec_edit_open_screen()`: `edit_submitted_form.php`, `edit_product.php`, `edit_calendar_event.php` with `?id=`). `denied` or `missing`: no row.
- Rights: the item's writer (`pg_rec_edit_rights()`); a product by `pg_preview_as_source_right('product')`; an event by that right and `validate_calendar_event_access()`.
- Asked once per widget and kept record (`_sdRecOpen`, key `sid:record`). Before the answer comes the row follows the widget's kind (a form view: edit), so the first menu of a widget may show a row the answer then takes away.

## What does a click do?
- Edit: `openPreview()`, then `_sdRecEdit.want = {node}`; the part turns the switch on after `record_edit_read` answers `ok` without `waits` and focuses that element's frame (`focusWanted()`); a waiting change leaves the bar's link. A plain Preview clears a stale want.
- Open: the screen in a new tab. Without an answer yet a blank tab opens inside the click (the pop-up blocker) and is led there, or closed with "This record cannot be opened from here."

## What does the Preview bar show for a product or an event? (R1)
- Where no collection item is drawn, the first product or event widget's record is asked with `preview_record_open` (`otherRecord()` in the part, which now loads for any drawn record); `ok` puts the link "Open the record" ↗ where the switch would be, in the bar and the narrow sheet. The arrow is a character: the site's page may load no icon font.

## What did it cost?
- Core bundle +2.9 KB (+1.0 KB gzip), part `recordedit` +1.0 KB (+0.4 KB gzip). Nothing on the canvas's drawing paths: the row's walk runs when a menu or the palette opens.

## Which test guards it?
- `test_record_edit_open_*` in `tests/record_edit_test.php`; `test_record_edit_preview_canvas_row` and `_link_for_other_kinds` in `tests/record_edit_preview_test.php`.

Related: docs/memory/designer/record-edit-in-place-preview.md, docs/memory/designer/designer-record-edit-drawing-save.md
