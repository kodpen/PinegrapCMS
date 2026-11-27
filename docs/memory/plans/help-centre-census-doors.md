---
name: help-centre-census-doors
description: Help centre census: the doors a support request comes in by today (e-mail, WhatsApp and SMS, a site form, Live Chat, My Account, the App Studio template's Support Requests) and where each lands
---

## E-mail, WhatsApp and SMS?
- A mail to a Workspace mailbox becomes a conversation (`ws_mail_ingest()`, `includes/workspace/mail.php:886`; the new row `:1017`), a card in the box's channel or room, with the customer's lines (`ws_mail_customer_lines()`, `:1029-1030`); then rules, notices and the automatic answer (`workspace/mail-overview.md`).
- WhatsApp and SMS land in the same tables (`workspace/mail-phone-decisions.md`).

## A site form?
- A submission is a `forms` row with a 10-character reference code (`generate_form_reference_code()`, `includes/fn/forms.php:4309`; `custom_form.php:708`), the form's mails (`custom_form.php:1623`), `form.submitted` queued (`:1790`), a survey heard (`:1794`) and a bell notice (`:1869`).
- It reaches the Workspace only by a flow: the template "Web form to the channel" writes it as a channel message (`includes/workspace/flow_templates.php:359-365`); a scheduled lead action can follow it (`includes/workspace/scheduled.php:5179`). It never becomes a mail conversation, so it has no state, holder or answer time.

## Live Chat?
- A site chat is a `chat_conversations` row with channel `site` and its `chat_messages` (`includes/migrations/2026.4.2.php:69-71`), answered in the panel's chat window by the operator (`pg_chat_site_operator()`, `chat.php:2028`).
- Out of hours the visitor's message opens it with an offline note (`chat.php:2401`), and the operator gets a bell (`:2428`) and an e-mail with Reply-To the visitor (`:2455`).
- It is apart from the Workspace: no box, holder or answer time; states open and closed only (`pg_chat_close()`, `chat.php:1458`).

## My Account?
- Its sections are order history, subscriptions, bookings, store credit, tickets (event tickets), referral and volunteering (`_pg_my_account_extras()`, `includes/fn/widgets_account.php:1957-1980`); no requests section. Its only request link is the privacy request (`:2002`).

## A design template's Support Requests?
- The App Studio template builds one from a form: a request is a form record, Status an office-use-only field, the messages the page's comments mailed both ways (`includes/design_templates/pinegrap-appstudio/support.php:12-17`).
- My Support Requests lists the submitter's own (`support.php:338-360`; `pg_sw_submitted_form_visible()` with `submitter_only`, `includes/fn/widgets.php:2653`); its states are in `pinegrap-appstudio/parts.php:350`.
- The team works it on the panel's forms screen, not in the Workspace; a site built from the template keeps these records if requests move to the Workspace.
