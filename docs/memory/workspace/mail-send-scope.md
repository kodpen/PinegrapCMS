---
name: mail-send-scope
description: Sending Workspace e-mail in a person's name over the API and MCP: the workspace:mail_send scope, who holds it, the gate that refuses a key, the send tools, the log line
---

## What is the scope?
- `workspace:mail_send`, row "Send e-mail" ("Sends e-mail from the Workspace mailboxes in your name") of `ws_scope_groups()`, a write with no read of its own. `ws_owner_scopes()` gives it to every team member; which mailbox they may send from is checked at Send (`ws_mail_send_check()`).
- Owner (2026-11-14, channel 2714): a person's connected chat may send, in the person's name, under a scope of its own (`workflow/decisions-made-workspace-26.md`).

## Which callers hold it?
- A team devices session: added by `api_device_scopes()` whenever it holds `workspace:write`, so the apps that sent before keep sending.
- An OAuth connection: only when the operator's ceiling for the OAuth app lists it and the person ticks it on the consent screen. No connection of before the scope holds it.
- It is in `api_plugin_scopes()` (`api_plugin_writes()`), the assistant plugin's set; it moves no money.
- An application key never sends: `ws_api_mail_send_gate()` (`ws_api_person_only()`) answers 403 whatever the key's scopes say. A channel agent, the Claude application (a key) and Pinegrap AI keep drafts only (#2444 answer 7).

## Which routes carry it?
- `workspace.mail_conversations.reply` (e-mail, or WhatsApp for a WhatsApp conversation), `workspace.mail_messages.send` and `workspace.mail_drafts.send` (POST /workspace/mail/drafts/{id}/send): all MCP tools, all with `dry_run`; the tool annotation marks them destructive.
- A draft is sent through `ws_mail_send()` (an answer by its `conversation_id`, with the draft's Cc and Bcc; a new mail by `ws_api_mail_new_mail()`), then deleted (`ws_api_mail_drafts_send()`).
- The panel's checks hold: the bounce list, the hourly limit (429), 409 `unknown_domain` with `send_anyway`.

## What does the activity log say?
- `ws_api_log()` after a send: "E-mail sent from the Workspace to …" or "WhatsApp answer sent …", with "(API: <application>)", in the person's name. The mail itself is the person's (`user_id`, name, Signature).

## Tests?
- `tests/workspace_api_mail_test.php`, `workspace_api_mail_phone_test.php`, `workspace_mail_drafts_test.php`, `api_plugin_scopes_test.php`. No route was called against a database or a mail server.
