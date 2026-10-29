---
name: required-boxes-and-seo-counter
description: Boxes the server refuses blank carry required (Delete in the same form gets formnovalidate); the SEO character counter is the field's description and says count, target and state in words
---

## Which boxes carry required although the save checks them on the server?
- Every box `validate_required_field()` refuses blank carries `'required'=>'required'`, so `pgInjectAriaAndRequiredMarkers()` marks its label (`panel-accessible-names.md`). Added for Order Report Name, Visitor Report Name, theme file Name, Edit Comment's Comment and Find & Replace's Find; before, people learned it only from the error after Save.
- A Delete submit button in the same form carries `formnovalidate`: the `data-confirm-content` handler lets it submit natively, and a blank required box would otherwise block the delete.

## How is the Form Editor box on Edit Submitted Form named?
- The liveform field has `id="form_editor_username"` and its `<label for>` points at it; before, the label had no `for` and the box was unnamed.

## What does a screen reader hear from the SEO character counter?
- `renderSeoCounter()` in `initSeoCounters()` (`backend.src.js`) appends the counter id to the input's `aria-describedby` (appended, never replaced) and hides the visible `45/60 ↑` and the bar with `aria-hidden`.
- A `visually-hidden` sentence says the count, the target range and the state: "No text yet. Aim for {var:1} to {var:2} characters." or "{var:1} characters, too short / good length / too long. Aim for {var:2} to {var:3}.". The keys sit in the panel translate map (`includes/fn/output.php`) and are read with `pgLang()`.
- Used by Edit Page, the product screen, Mass Edit, Site Settings > SEO and the designer's page settings; none needed its own edit.

Test: `tests/required_markers_and_seo_counter_test.php`.
