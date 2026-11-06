---
name: booking-edit-form-fields
description: The booking screen (edit_booking.php, new and existing) draws every field as a labelled form-field box; the help texts are form-text the fields name (step B11)
---

## How are the booking screen's fields drawn?
- `$booking_field()` in `edit_booking.php` prints one `<div class="form-field mb-5">` with `<label class="form-label" for>` and the liveform field; its fifth argument is a help text, printed as `<div class="form-text" id="<name>_help">` and added to the field's `aria-describedby`.
- The Service and time card (new booking) puts Staff, Service and Date in `form-field` boxes on a `row g-5`.
- The hint that an e-mail address or a phone number is needed is one `form-text` (`#booking_reach_help`) both fields name.
- The cancel reason's help text goes through the fifth argument, so it is a `form-text` in the same box.

## What stayed?
- The wide layout (form column, sticky status column, save bar) was already there; the columns, the facts list, the draft kept across the choice card's reload and the save logic did not change.

## What did it replace?
- Bare `mb-5` wrappers, a loose `<p class="form-text">` hint no field named, and a `small` paragraph for the cancel reason's help.
- Pinned by `tests/field_caption_links_test.php` (`test_field_caption_edit_booking_form_fields`).
