---
name: mail-phone-media
description: A customer's WhatsApp picture, video, voice message or document fetched by the general job into the conversation card's files (WA14c, #9480), no new column; what is given up and why
module: whatsapp_media
---

## How does a received file reach the card?
- The webhook keeps only the media id and type (`whatsapp_inbox.media_id`, `media_mime`). The general job (`job.php`) runs `pg_whatsapp_media_run(5, 20)` after the inbox sweep (`includes/fn/whatsapp_media.php`).
- It takes WhatsApp items (direction in, box transport whatsapp) of the last 24 hours (`PG_WHATSAPP_MEDIA_WINDOW`) whose inbox row (by wamid = `rfc_message_id`) has a media id, with no file message yet and an empty `refused_files`. No column marks it: the file message and `refused_files` are the state.
- `pg_whatsapp_media_fetch()`: `GET /<media id>?phone_number_id=` (`pg_whatsapp_cloud_media_info_request()`), then the address Meta gives (https only, a few minutes) with the token, at most 16 MB (`PG_WHATSAPP_MEDIA_MAX`, read into memory; Meta allows documents of 100 MB).
- `pg_whatsapp_media_keep()`: Files' upload check (`ws_upload_inspect()`), `ws_store_file_bytes()` in the channel's folder (the box's creator as owner), a file message under the card (`ws_mail_message_write()`, sender the item), as an e-mail's attachments. Never on a demo site or with WhatsApp off.

## What is the file called?
- `pg_whatsapp_media_file_name()`: a document's own name when the message's text is a name with the type's ending; else `whatsapp-<kind>-<YYYYmmdd-HHiiss>.<ending>` (UTC). Endings by `pg_whatsapp_media_extension()`: JPG, PNG, WebP (stickers), MP4, 3GP, OGG/MP3/M4A/AAC/AMR (voice), PDF, TXT, Word, Excel, PowerPoint.

## When is a file given up?
- Written in the item's `refused_files` and never asked for again: a type not kept, over 16 MB, Meta saying it is not there (codes 100, 131052, 131053), or the Workspace refusing it (upload rules, archived channel).
- Asked for again on the next run, for a day: Meta not reached, a refused token (190), any other passing fault. After the day it is left; the card's "[A picture]" stays.

## How was it verified?
- `tests/whatsapp_media_test.php` (requests, the answer read, names, limits, what is given up, wiring). Sandbox: a picture through ingest, a pass against Meta with a stand-in token (left pending), the keep step with real bytes (a file message under the card; the next pass skips it). A real fetch from Meta was not seen.

Related: docs/memory/workspace/mail-phone-conversations.md, docs/memory/platform/whatsapp-webhook-events.md
