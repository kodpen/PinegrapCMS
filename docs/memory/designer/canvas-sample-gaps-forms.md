---
name: canvas-sample-gaps-forms
description: CS0 census, forms (CS3): custom form, form list and item views, form e-mail cards; what a field of each kind and the record values draw on the canvas
---

## What does a form field's value draw today?
- A field of the widget's form (`_sdFormFieldOf()` in `_displayValueForBinding()`): date and date-and-time the date sample, time "14:30", e-mail address "sample@example.com", text area its ‹label›, every other kind "Example": text box, pick list, radio buttons, check box, file upload, signature, information.
- So a record has no value of its kind: a choice is not one of its options, a file is not a file name, a check box is not yes, long text is a ‹label›, information HTML is "Example", and every field of a card reads the same "Example".
- A field's label (`<field>__label`) reads the field's own label once the fields are loaded (asked once, then redrawn): a sample.
- A field of a referenced record (`__ref`) draws "Example".

## What do the record's system values draw? (`SW_STANDARD_FIELD_OPTIONS`)
- All samples (`_SD_STANDARD_FIELD_PREVIEWS`): reference code, dates, submitter, counters, newest comment, ids. Only `form_item_view` (the detail address, old name) is an address.
- Form item view extras: the new account values and `__ai_summary` have samples; the AI links are addresses.
- Record entity values (`SW_FORM_RECORD_ENTITY_TOKENS`): `__quiz_score`, `__post_date`, `__post_author` ‹label›; `__post_title` "Example"; `__post_summary` the stock blurb; `__post_image` grey box.

## What does the form list view draw?
- `__filter_value` ‹label›; `__result_count`, `__page_number`, `__page_count` samples. Lists `form_record.values`, `.split`, `.files` and `paging.pages` have row samples (`_SD_LIST_ROW_PREVIEWS`; `SD_LIST_SAMPLE_ROWS` for the pager).
- The first card draws "Example" in every field; the records after it are the site's real records drawn by the server (`_SD_GHOST_TYPES`, `designer-widget-ghosts-and-edit-chips.md`), so a form with no records shows one card only.

## What does the custom form widget draw?
- Its controls are drawn as controls from the form's fields. Bound values: `__form_title` and `__submit_label` "Example" (the page writes the form's own title and button label), `__success_message` ‹label›, `__error_message` a sample.

## What do the form e-mail cards draw?
- The templates' "e-mail to sender" and "e-mail to admin" pages are form item view widgets on the contact form (`email_message_sender`, `email_message_admin` in `includes/design_templates/*`): the record values above, so the visitor's message, name and topic read "Example" or a ‹label›.
