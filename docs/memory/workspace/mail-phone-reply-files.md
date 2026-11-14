---
name: mail-phone-reply-files
description: A WhatsApp answer's file and template (WA14b, #9446): Meta's kinds and sizes before the queue, the upload at the send, templates with typed values after the 24 hours, Send again
module: whatsapp_reply
---

## Which files and templates?
- The File field is the panel's picker field (`pg_picker_field('channel_file', 'file_id')`, markup sent by `ws_mail_phone_reply_form()`), a file of Files the writer may open, read by `ws_library_file()` as the writing box's "From the file manager"; architect 2711 after the owner's 2709. A copy is kept in the channel.
- A file: `pg_whatsapp_reply_media()` before anything is kept: JPG/PNG 5 MB, MP4/3GP 16 MB, PDF, TXT, Word, Excel, PowerPoint 16 MB (Meta takes 100 MB, but the queue reads the file into memory); a GIF is refused. Files' own upload check first (`ws_upload_inspect()`). Caption at most 1024.
- The drawer after the 24 hours: Template (`ws_mail_phone_reply_form()` sends `pg_whatsapp_reply_templates()`), the chosen one as the customer reads it (owner 2709: a template choice shows a preview): the designer's `.pg-wa-msg` bubble, typed values in place as they are typed (`whatsappPreview()`),
- Below it a field per variable as the designer named it; a marketing one says it needs consent; none: the WhatsApp Messages card's "No approved template can be chosen yet".
- A template: `pg_whatsapp_reply_templates_offered()` = `pg_whatsapp_message_offered()` (approved, no media header, no button needing more than text). `pg_whatsapp_reply_template_fill()`: every variable typed, components by `pg_whatsapp_message_components()`, the text the card keeps. An offer code is filled by the queue.
- A marketing template or an offer code without marketing consent is refused before the write. Queued by `pg_whatsapp_enqueue()`, kind `reply`, the answer's reference, no expiry; consent asked again at the send.
- A file's payload: `{"type":"image|video|document","file_id","name","caption"}`.
- At the send `pg_whatsapp_reply_send_media()` reads the file through `pg_file_local_copy()`, checks it again, uploads it (`POST /<phone id>/media`, multipart, `pg_whatsapp_cloud_media_upload_request()`) and sends it by its id (`pg_whatsapp_cloud_media_request()`; a document keeps its name). A failed upload sent nothing: retried by Meta's code, never uncertain. A file gone or grown past the limit fails the row for good.

## What does Send again send?
- `ws_mail_phone_resend_send()`: the last queue row's template (at any time; a row holding a single-use offer key is refused, its key was switched off when it failed), else the message's file, else the text; the last two only inside the 24 hours. A template row already swept (30 days) falls back to the text.

## What is left?
- A received picture, voice message or document fetched into the card's files: WA14c (`plans/whatsapp-channel-steps-2.md`).
- `tests/whatsapp_reply_media_test.php`: kinds and sizes, the file row, the upload and media requests, template fill and list, wiring. No Meta account: a file or template answer was never delivered.

Related: docs/memory/workspace/mail-phone-reply.md, docs/memory/platform/whatsapp-template-approval.md
