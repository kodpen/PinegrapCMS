---
name: mail-undo-send
description: Undo send (IB13): the panel's Send keeps a mail waiting 10 seconds with Undo on a notice; then the browser sends it, the job as fallback; Undo turns it into its draft
---

## What does the person see?
- Send on New e-mail, Forward as e-mail and Reply by e-mail closes the form and shows a dark notice "Sending in 10 seconds." with "Undo" (Turkish "Geri al"). After the 10 seconds the notice closes and "The e-mail is on its way." follows.
- Undo opens the same form again as a draft, its files back in its Files list, with Save draft beside Send; notice "Not sent. The e-mail is back in its form as a draft.".
- A time chosen with Send later (`mail-send-later.md`) is not touched: such a mail gets no Undo notice.

## How does it work?
- The browser posts `undo=1` when no Send later time is set; `ws_mail_undo_send_at()` (pure, `mail_later.php`) gives `ws_mail_send()` a `send_at` 10 seconds ahead (`WS_MAIL_UNDO_SECONDS`, no setting), so the mail is written as IB6's waiting mail (`send_state` `later`). The answer carries `item_id` and `undo` (the seconds, `ws_mail_undo_offered()`).
- After the wait the browser calls `ws_mail_later_send` (Send now's action). A page closed in between leaves it to `ws_mail_later_run()` in the general job, so it leaves at the job's next run.
- The API and MCP never send `undo`: they send at once as before. Before step 11.0.207 the panel sends at once too.

## What does Undo change?
- Action `ws_mail_undo_send` (`ws_mail_undo_send()`, its turn in `ws_mail_later_to_draft($item, false)`, shared with the job's held mail, `mail-send-later.md`): only the writer (`ws_mail_undo_is_own()`), only while the mail waits; the item is claimed later to draft in one UPDATE, so Undo, Send now and the job never both win.
- The item becomes a draft (`mail-drafts.md`): message id, threading and send time cleared, a draft hash; a new mail's draft gets `conversation_id` 0.
- Its messages in the channel (card or answer, file lines, a forward's line) are deleted as `deleted_at` rows and touched, and the files it stored there are removed (`ws_upload_discard()`); the browser still holds the form's files and gives them back (`undoDraft()` in `workspace_mail.js`).
- A new mail's conversation is deleted when nothing else is in it. An answer's conversation takes back its times and status (`ws_mail_undo_conversation_fields()`, pure): `last_out_at` the last other mail sent, `first_reply_at` cleared when it was this answer, "waiting" back to "open" when the customer wrote last.

## Which notice?
- The panel's toast (`toast()` in `workspace.js`) takes `action.onClick` (a button that closes the notice) and `action.delay`, and returns `{ hide }`.

## Tests?
- `tests/workspace_mail_undo_test.php`: the wait, who may undo, the conversation's fields, the wiring.
