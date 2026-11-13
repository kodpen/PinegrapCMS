---
name: canvas-sample-gaps-forms
description: CS0 census, forms: what custom forms, the form list and item views and the form e-mail cards still draw without a sample after CS3 (#9239)
---

## What did CS3 settle?
- A record's field values draw answers of their kind (`canvas-sample-form-record.md`); the record entity values, the address filter value and the custom form's title, button and success message have samples. On the Market template's help, application, blog and e-mail pages no value draws ‹value› or "Example" any more.

## What is left?
- `__post_image` and every bound picture draw the sample photo (`canvas-loop-as-published.md`, #9233).
- A field of a referenced record (`__ref`) still draws "Example".
- A form list with no records shows its first card only; the records after it are the server's real ones (`designer-widget-ghosts-and-edit-chips.md`).
