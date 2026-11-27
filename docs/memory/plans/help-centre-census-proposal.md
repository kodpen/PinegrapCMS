---
name: help-centre-census-proposal
description: Help centre census proposal: a support request is a support-box mail conversation with a priority and a source, every door ends there; the columns and tables to ask one by one, the screens in words
---

## A conversation with more, or a thing of its own?
- Proposed: a request is a conversation of a support box. State, holder, first-answer target, automatic answer, rules, notices, `workspace.mail.closed`, the survey and the API are there today (`help-centre-census-conversation.md`).
- Against a thing of its own: it builds each of those again, gives the team a second list, holder and clock, and its answers still go out by mail.
- The cost: a form, a chat and My Account must open a conversation in a chosen support box with the customer's address. A form has it from the field tied to the contact's e-mail (`contact_field` `email_address`, `custom_form.php:951`) or a signed-in user's; a form with neither cannot be a request. The customer's reply threads back, as the automatic answer's subject carries `[#T<id>]` (`mail_status.php:628`); the form's own mails (`custom_form.php:1623`) do not.

## Which columns or tables, and why?
- `ws_mail_conversations.priority` TINYINT (Low, Normal, High, Urgent): on the row, not a side table, because every list, filter, count and the API read it; status columns already sit there unused for plain boxes (`workspace/mail-support-box.md`).
- `ws_mail_conversations.source` VARCHAR(10) (mail, whatsapp, sms, form, chat, account) and `source_id` INT (the `forms` or `chat_conversations` row): the list's label and the link back.
- A resolve-by time: no column; computed from `first_in_at` and the box's hours per priority, as the first answer is (`mail_status.php:449`). A clock stopped while waiting needs a stored column, asked only if wanted.
- More states: "Solved", closed by itself after the box's days without an answer, changes the `status` ENUM: asked on its own.
- The number: the conversation's id, already `[#T<id>]` in every answer's subject; no new column.
- `kb_votes` (id, form_id, record_id, answer yes or no, created_at) for "Was this helpful?": no address or IP; a cookie stops a second vote.
- If the owner wants a thing of its own: `support_requests` (number, customer, contact_id, user_id, state, priority, holder, times, source) and `support_request_messages` (direction, author, text, files) with their own sending; each asked.

## Which screens, in words?
- Incoming e-mail screen: a Priority column and filter beside state and holder; the source as a small label (Form, Chat, Account); the reading pane gets a Priority select beside the state and holder selects.
- The box form, under "Support box" beside "First answer within (hours)": answer hours per priority, the days before Solved closes.
- Form settings: a "Support request" switch with the box and the e-mail field; the panel's chat window: a "Make a request" button opening one with the chat's text.
- Site: the Search Results widget's "Search only in"; an article's "Was this helpful?" Yes and No; a request form's subject offering matching articles as the visitor types.
- My Account "My requests": number, subject, state, last answer; one opens to the mails in and out only (`ws_mail_items`), never the channel's notes, with a Reply box if the owner says so.
- Articles: no new screen; votes show as two counts on the collection's record list.

## What could the code not settle?
- How many sites keep a support box, so how many rows a new column meets: the code holds no such figure.
- How the App Studio template's Support Requests move over, or stay as they are.
