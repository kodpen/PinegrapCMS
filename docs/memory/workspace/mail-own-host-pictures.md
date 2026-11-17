---
name: mail-own-host-pictures
description: E-mail pane: Show pictures is offered only for pictures on another host; a picture on the site's own address is never loaded and a line says so (#10079)
---

## Why is a picture on the site's own address never shown?
- The frame's script (`pg_guarded_frame_mail_script()`, `outside()`) puts a set-aside address back only when its hostname is not the frame's own: a mail must not call the panel with the reader's cookies. Loading such pictures without credentials was not taken.

## When is Show pictures offered?
- SQL marks a mail with any set-aside address (`outside_pictures`, `ws_mail_pane_columns()`). `ws_mail_pane()` then reads `body_html` of the marked mails only and asks `ws_mail_picture_hosts($html, ws_mail_own_hosts())`: `outside` keeps the offer, `own` sets `own_pictures`.
- Own hosts: `HOSTNAME`, `HOSTNAME_SETTING` and the request's Host, lower case, no port. A sibling host (`shop.` of the same domain) counts as outside.

## What does the pane say?
- With `own_pictures`: "Pictures on this site's own address are not shown in an e-mail." before Plain text, beside Show pictures when that is offered too; pressing Show pictures does not hide it.

## What did it fix?
- #10079: a site's own order mail with its logo on the site's host offered Show pictures; pressing it hid the line, remembered the sender and the picture stayed broken.
