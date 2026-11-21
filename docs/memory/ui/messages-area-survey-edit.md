---
name: messages-area-survey-edit
description: Add and Edit survey (edit_feedback_survey.php, FD2): Question, Sent after, Sent by, Low scores, After the answer cards, status column, the texts JSON and filters, Send me a test
module: feedback_surveys
---

## How is the edit screen laid out?
- `edit_feedback_survey.php` on the edit layout (form column, status column, save bar), gate `manager`. Five cards follow.
- Question: name, kind as a segmented choice, question and comment label per site language (tabs when the site has more than one), Extra questions folded: three slots, short text or single choice.
- Sent after: the touchpoint, its filter, the delay in hours or days, at most 90 days; the periodic one: every N months, active in the last M months.
- Sent by: E-mail, SMS, WhatsApp; Service message or Commercial, the periodic one Commercial fixed. Low scores: fallback user, the user who joins after 48 hours, a public Workspace channel.
- After the answer: the review invitation switch, a line and link to Google Reviews when no place is set.
- An empty question or comment label uses `pg_feedback_default_texts()` for the kind, shown as the field's placeholder.
- Status column: the On switch with its pill, the e-mail preview (`pg_feedback_mail_html()`, buttons not links), Send me a test, Delete or Archive. Script `assets/js/feedback_survey.js`, served as written.

## What is stored?
- `pg_feedback_survey_input()` (pure) reads the form: ids the screen did not offer read as 0, a filter keeps only its touchpoint's keys. Filters: booking `service`, ticket `event` (events with reservations), course `course`, form `form`, `field` (a pick list or radio field, 0 for "when it is sent") and `value`, mail `box`, periodic `every` and `active`; delivery none (`filter` NULL).
- `texts`: `{"question": {lang: text}, "comment": {lang: text}, "extra": [{"type": "text"|"choice", "label": {lang: text}, "choices": {lang: [..]}}]}`; an extra question without a label in the site's first language is dropped; a choice needs two choices. Languages: `pg_feedback_survey_languages()`, the translation source first.

## What does Send me a test do?
- From the edit screen it saves, then sends; from the list it sends the stored survey. The e-mail in the site's first language to the signed-in user's address, subject "[Test] <question>", through `email()`; no answer row, buttons lead nowhere (FD4 links them). Ten an hour per user: `waf_rate` bucket `feedback_test|<user id>` (`PG_FEEDBACK_TEST_HOURLY`).
- SMS and WhatsApp surveys are tested by e-mail too, until FD4 builds their message.

Test: `tests/feedback_surveys_test.php`.

Related: docs/memory/ui/messages-area-surveys-tab.md, docs/memory/platform/feedback-store.md
