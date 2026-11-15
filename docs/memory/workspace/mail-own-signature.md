---
name: mail-own-signature
description: A person's own e-mail signature (IB3, #9513): My e-mail settings on the E-mails toolbar, ws_profiles.mail_signature, where it stands in a sent mail and in the forms' previews
---

## What is it?
- Owner (2026-11-14): a signature per person (`workflow/decisions-made-workspace-27.md`). Plain text, the rules of a mailbox's Signature (`ws_mail_signature_input()`: 1,000 characters, 12 lines), kept in `ws_profiles.mail_signature` TEXT NULL (step 11.0.203); empty is NULL, so the writer's name stands as before.
- Read by `ws_mail_own_signature()`, written by `ws_mail_own_signature_save()` (insert-or-update, as `notes_pinned`); `ws_mail_own_signature_ready()` probes the column.

## Where is it set?
- "My e-mail settings" on the E-mails screen toolbar (`ws_mail_list_my_settings_html()`, `data-ws-mail-my-settings`), drawn for whoever may send from a box. It opens the drawer `#ws-mail-my-settings` (`mySettings()` in `workspace_mail.js`) with a live preview.
- Actions `ws_mail_my_settings` (signature, writer, limits) and `ws_mail_my_settings_save` (field `signature`), rows of `pg_panel_actions()`; people only. No API route or MCP tool.

## Where does it stand in a mail?
- Under "-- " in place of the writer's name, the box's Signature (or its name) under it: `ws_mail_writer_lines($name, $own)` gives the lines, which go into `ws_mail_send_body()` / `ws_mail_answer_body()` / `ws_mail_answer_html()` as the writer.
- Read when the mail leaves: `ws_mail_send_properties()` takes the item's `user_id`, so Send again carries the signature of that moment; `ws_mail_reply_send()` takes the viewer's. The automatic answer has no writer and stays as it was.
- Plain text in an HTML mail too, escaped line by line; an HTML signature is later, asked of the owner again once MA10b's writing has landed (architect, channel 2863).

## What do the forms show?
- `signatureLines()` in `workspace_mail.js` draws the same lines for New e-mail, Forward as e-mail and Reply by e-mail: `ws_mail_compose` and `ws_mail_forward` send `own_signature`; `ws_mail_reply_form` sends `writer`, `own_signature` and `box` (name, signature).

## Tests?
- `tests/workspace_mail_own_signature_test.php`. The step was run twice on a sandbox DB; the drawer and New e-mail were shot on a sandbox (Reply by e-mail's preview was not: no conversation seeded). No mail was sent.
