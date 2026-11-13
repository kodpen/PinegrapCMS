---
name: canvas-sample-form-record
description: What a form record draws on the designer canvas (CS3, #9239): an answer of each field kind, fields fetched by the first value, fixed record samples
---

## What does a form field's value draw?
- `_sdFormFieldSample(ff)` in `style_designer.src.js`, for a field of the widget's form (`_sdFormFieldOf()`): date and date-and-time the date sample, time 14:30, e-mail address an address, pick list and radio button the field's first choice, check box its first choice or "Yes", file upload `document.pdf`, signature "Signed", information its information text without markup.
- Long text: a message, comment, note or question (by its label) reads as a visitor's question; any other text area, and the RSS description role, a few neutral sentences. The RSS title role a post title.
- A text box: its default value (`default_value`), else the words of its label or name (`_SD_FIELD_WORD_SAMPLES`, English and Turkish): e-mail, phone, address, company, city, web address, subject, name, message, number; else "<label> sample".
- `first_name` / `last_name` (standard previews) are the two halves of the sample name `_sdT('Alex Morgan')`, so a sender written as both reads as one person.

## Where do the fields come from?
- The panel's `list_form_fields` (`includes/panel/shared_component.php`) now also returns `information` and `default_value`. The canvas asks once per form when a field's value or label is drawn (`_sdLabelFieldsAsked`) and paints again when they arrive. Before CS3 only a field label asked, so a record card drew ‹value› until a panel loaded the fields.
- The column is `default_value`, not `value`: a wrong column name makes the query fail and every field list empty (no error on screen).

## Which record values draw a fixed sample?
- `_SD_SHORT_LABEL_TOKENS`: `__quiz_score` 80%, `__post_title`, `__post_date`, `__post_summary`, `__post_author`, `__filter_value` (design), `__form_title` (Contact Form), `__submit_label` (Submit), `__success_message`.

## How is it tested?
- `tests/designer_canvas_form_samples_test.php` runs `_sdFormFieldSample()` under Node for every kind and the text box rules.
