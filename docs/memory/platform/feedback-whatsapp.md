---
name: feedback-whatsapp
description: A satisfaction survey's WhatsApp message (FD4b, #11111): the template chosen on the Sent by card, kept in the texts JSON, its values, rows waiting while it cannot be sent, kind feedback
module: feedback_whatsapp
---

## Where is the template chosen?
- On the survey's "Sent by" card (`edit_feedback_survey.php`), shown while WhatsApp is the channel (`feedback_survey.js`): "WhatsApp template", one of the account's approved templates a text can fill (`pg_whatsapp_message_templates()`), and a list per variable (`pg_feedback_whatsapp_card_html()`, field prefix `fs_wa`, names from `pg_whatsapp_choice_var_name()`).
- Values (`pg_feedback_whatsapp_value_names()`): Store name, Question, Answer link (`feedback.php?t=…`), Answer link code (the token alone, for a URL button whose address in the template ends in `feedback.php?t=`).

## Where is it kept?
- In `feedback_surveys.texts` under `whatsapp`: `{"template": "name|language", "vars": {slot: value}}`; no column. `pg_feedback_survey_decode()` gives `$survey['whatsapp']` (`pg_feedback_whatsapp_from_texts()`); `pg_feedback_survey_columns()` writes it only when a template is chosen.

## What does a save refuse?
- `pg_feedback_whatsapp_input()` checks only a WhatsApp survey whose template is offered: every variable filled, a value of the list. No template, or one no longer approved (listed "(not approved)"), saves as it is: its rows wait. By e-mail or SMS a half-made choice is kept silently.

## When do its rows wait?
- The pass takes due rows with `pg_feedback_due_channel_sql(pg_feedback_whatsapp_ready_surveys())`: e-mail and SMS always, WhatsApp only for switched-on surveys whose template is offered and fully filled (`pg_feedback_whatsapp_choice_ready()`), and none while `pg_whatsapp_ready()` is false. So the waiting rows never fill the pass's 200 places.
- A queued row whose link ran out (due more than 30 days ago) is cancelled by the pass ([[feedback-send-pass]]).

## How is it queued?
- `pg_feedback_message_send()` calls `pg_feedback_whatsapp_send()`: `pg_whatsapp_message_components()` fills the template, `pg_whatsapp_enqueue()` kind `feedback`, reference `feedback:<id>` (`:r` for the reminder, whose Question value starts "Reminder: "), the row's contact, a day's expiry. A template that cannot be filled logs a line and cancels the row.
- Consent (poll 198): the pass checks a commercial survey's number (`pg_sms_marketing_permission()`) before the send; a MARKETING template asks again at enqueue and at the send, whatever the survey's kind, so the card asks for a utility one.

## How was it verified?
- `tests/feedback_whatsapp_test.php`, `tests/feedback_answer_test.php`. No database, no Meta account and no real send in the cloud session.

Related: docs/memory/platform/feedback-answer.md, docs/memory/platform/feedback-send-pass.md, docs/memory/platform/whatsapp-overview.md, docs/memory/ui/messages-area-survey-edit.md
