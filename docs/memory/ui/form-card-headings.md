---
name: form-card-headings
description: Create and edit screens on includes/forms/*.php: card titles are h2.h5, group titles inside a card h3.hN, only dialog titles keep h4-h6; why the look did not change
---

## Which element is a card title on a shared form?
- `<h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">`, not a `<div>`; the user rights cards in `user_form.php` use `<h2 class="text-uppercase h5 fg-primary fw-bold mb-0">` inside their flex header. The screen's frame prints the h1, so the outline is h1 → h2 → h3.
- The symptom it fixed: the titles looked like headings but a screen reader's heading list jumped from h1 to stray h5 group titles and never showed the card names.

## Which element is a group title inside a card?
- `<h3 class="h5 …">` (was `<h5>`), `<h3 class="h4 …">` for Shipping Cut-offs, `<h3 class="h6 …">` for field_form's rows-and-columns title. The page_form Form List Views and Calendars titles follow the same rule.
- A sentence is never a heading: shipping_method_form's "For reference, the current site time is …" is `<div class="h6">`.

## Why does it look the same?
- In `backend.src.css` the `:where(hN,.hN)` rules have equal specificity and the smaller size comes later, so `<h2 class="h5">` keeps the h5 size; Bootstrap 6's reboot gives `.hN` the element's weight and margin; `.card-header`'s `margin-bottom:0` still wins. The section rail selects `.card > .card-header` whatever the element; no panel CSS selects `div.card-header`.

## What stays as it was?
- Control-only card headers (calendar_event_form's Select All switches), field_form's `<label class="card-header">` Field Type title (`tests/content_edit_names_test.php`), field_form's inner Format/Example cards, and `h5.dialog-title` dialog titles (`product_group_form.php`).

## Which test keeps it?
- `tests/form_card_headings_test.php`: no `div.card-header.h5` or `span.h5` in `includes/forms/`, no h4-h6 element there but dialog titles, counts of h2 titles and the h3 group titles.
