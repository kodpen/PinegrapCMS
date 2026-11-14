---
name: whatsapp-form-messages
description: A custom form's WhatsApp message to its sender (WA6, #9374): number field, template, a field per variable, connect_apps whatsapp_forms, sent with the e-mails, off by default
module: whatsapp_forms
---

## Where is it set?
- On the custom form page's settings, after "E-mail Submitter": "WhatsApp Message to Submitter" (`pg_whatsapp_form_section_html()`), drawn once the form has fields. A switch, "Mobile Number Field", "Template" and one list per variable.
- The number field is one of the form's text boxes, the one filling the contact's mobile phone listed first (`pg_whatsapp_form_phone_choices()`).
- A variable takes the store's name, the record's reference code or any field but an information block or a file upload (`pg_whatsapp_form_value_choices()`, values `store`, `reference_code`, `field:<id>`). A check box group's answers are joined with commas.
- Templates are those of the WhatsApp Messages card ([[whatsapp-order-messages]]): approved, without a media header or a copy-code button. The template list and variable lists come from `pg_whatsapp_choice_html()` and `pg_whatsapp_choice_decide()`, shared with the order events.

## Where is it kept?
- `connect_apps` row `whatsapp_forms`, `options` = `{"forms":{"<page id>":{"on":bool,"phone_field":id,"template":"name|language","vars":{"<slot>":"<value>"}}}}`; no schema. A copied form page does not take the choice with it, and a deleted page's entry stays unread.
- Saved by `pg_page_form_save_type_links()` (add, edit and the page type pane). A refused choice stops the save: `pg_whatsapp_form_errors()` is added to `pg_page_form_number_errors()` in `edit_page.php` and `page_type_pane.php` (a new page has no fields, so no section).

## When does it go?
- With the form's e-mails: `pg_cf_form_mails_send()` calls `pg_whatsapp_form_message()` unless a fee is owed, so a form with a fee sends it when the record is marked paid ([[custom-form-mails]]).
- Queued with kind `form`, reference `form:<form id>`, the record's contact_id and the two-day expiry. An empty number field, a number WhatsApp cannot take, WhatsApp not set up or a template no longer approved sends nothing; a template that cannot be filled writes an activity-log line.
- A marketing template goes only with marketing consent (`pg_whatsapp_enqueue()`); the section asks for a utility one.

## What is still to come?
- Variables show as `{{1}}` in the lists; the owner asked for chips that name what they stand for (channel 25, #2222). WA8's shared chip replaces them in both places once it lands.
- Like WA5 it may not be switched on anywhere before the owner's first live send.

## How was it verified?
- `tests/whatsapp_forms_test.php` (stored choice, choices, decision, answers, wiring). No database, Meta account or real send in the cloud session.

Related: docs/memory/platform/whatsapp-order-messages.md, docs/memory/designer/custom-form-mails.md, docs/memory/platform/whatsapp-outbox-queue.md
