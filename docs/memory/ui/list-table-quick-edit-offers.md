---
name: list-table-quick-edit-offers
description: All Offers list quick edit (offer_list_edit.php): the fields, the editor's whole-offer check and save behind each change, dates and open-ended offers, the row's chip data after a save
module: offer_list_edit
---

## Which cells change?
- Every list value `edit_offer.php` changes (`pg_offer_edit_columns()`): Offer (the code), Message, How (code or automatic), Status (enabled or disabled), Start Date, End Date. Text: Flow (conditions and results, edited only in the editor) and Last Modified. `tests/offer_list_edit_test.php` fails for a drawn cell that is neither.
- The list is still a DataTables screen (`includes/templates/view_offers.php`, moving to the driver in TD13), so the cells take the old click/F2 editor of `backend.src.js` (`list-quick-edit-keyboard.md`), not the driver's quick edit mode. The message, once under the code, is its own column; Validity became Start Date and End Date (sorted by `data-sort`, Y-m-d).

## How is a change checked and written?
- `pg_offer_quick_update()` loads the offer as the editor does (`_pg_offer_load()`), changes one value (`pg_offer_quick_request()`, pure) and sends the whole offer through `_pg_offer_validate()`, the key code check on a changed code and `_pg_offer_save()`: the editor's log, audit, `offer.updated`, key codes following a new code, shared rule/result rows copied.
- An offer the editor itself would refuse as stored refuses every list change, with that reason ("The offer cannot be saved as it is: … Open it to fix this first.").
- Rights: the editor's (`role < 3` or `manage_ecommerce`); `view_offers.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

## How do the dates work?
- Typed in the site's date format (`validate_date()`, error "Please enter a valid date for …" with the format). An empty End Date makes the offer open-ended (`PG_OFFER_OPEN_END_DATE`); with a start after today it stays a range up to that date so the start is not moved back to today, and reads as open-ended once it has started.

## How does the row follow a save?
- The answer carries every changed cell and `row` (status, how, incomplete, enabled). `backend.src.js` fires `pg:list-edit-saved` on the cell after a save; the template's listener updates `data-status`/`data-how`/`data-incomplete`, the power toggle's name, the chip counts, and redraws when the active chip no longer matches. The power toggle sets the Status cell's `data-pg-edit-value`.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/offer-editor.md, docs/memory/ui/offers-list-chips.md
