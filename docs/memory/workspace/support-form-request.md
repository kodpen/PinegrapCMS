---
name: support-form-request
description: A custom form's "Support Request" switch (HC3): box and e-mail field in connect_apps, the record taken in as a mail by ws_mail_ingest() with its automatic answer; the reply threads back
module: support_forms
---

## Where is it set?
- Create/Edit Page and the designer's Page Type pane, a custom form's settings, after "WhatsApp Message to Submitter": switch "Support Request" (`pg_support_form_section_html()`), drawn once the form has fields and the Workspace's mail tables are there.
- "Support Box": enabled e-mail boxes that are support boxes (`pg_support_form_box_offered()`, the same rule as `ws_mail_box_is_support()`; WhatsApp and SMS boxes are left out). "E-mail Address Field": the field tied to the contact's e-mail first, then e-mail address fields. "Subject Field" (optional): a text box, pick list or radio button; empty uses the form's name and the reference code.
- On without a box or an address field is refused with a sentence on the field (`pg_support_form_errors()`, beside `pg_whatsapp_form_errors()` in `edit_page.php` and `page_type_pane.php`). Off keeps the choices.

## Where is it kept?
- `connect_apps` row `support_forms`, `options` = `{"forms":{"<page id>":{"on":bool,"box_id":id,"email_field":id,"subject_field":id}}}`; no schema. Saved by `pg_page_form_save_type_links()`.

## How does a submission become a request?
- `pg_support_form_request()` is called by `pg_cf_form_mails_send()` beside the WhatsApp message: not while a fee is owed, once the record is paid.
- It builds a plain-text mail from the record (`pg_support_form_mail_raw()`): From the address field's answer, To the box, the subject, one line per answered field and the reference code; Message-ID `pg-form-<forms.id>@<host>`, so a second run is a duplicate. No Auto-Submitted header, so the loop gate takes it.
- `ws_mail_ingest($raw, $box, ['tie' => …])` makes the conversation as for any mail: route, rules, card, notices, `workspace.mail.received`, the automatic answer with `[#T<id>]` in its subject. The customer's reply to that answer threads back by the tag (`ws_mail_find_conversation()`).
- `source` form and `source_id` the record: the Incoming e-mail list's Form label and the pane's link back (`mail-priority-source.md`).
- `tie` (`pg_support_form_tie()`): the record's user and contact, each only when its address equals the request's. Without `tie` the intake ties a mail only when the box's server vouched for it.
- Quiet when off, the box gone or no longer a support box, or the answer is no address. A refusal of the intake (held, loop, no channel, an error) writes an activity-log line "submitted form (…) opened no support request".

## Tests?
- `tests/support_forms_test.php`: the stored choice, the choices, the post, subject and body, the built mail parsed back, the tie, the wiring. No database, mailbox or browser run in the cloud session.

Related: docs/memory/workspace/mail-support-box.md, docs/memory/workspace/mail-status.md, docs/memory/platform/whatsapp-form-messages.md, docs/memory/designer/custom-form-mails.md
