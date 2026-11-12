---
name: mail-html-preview
description: A mail's HTML in the E-mails reading pane (WM12, PR #8861): the guarded frame's mail mode, workspace_mail_frame.php, Plain text, Show pictures remembered per sender, the narrower rows
---

## How does the pane draw a mail's HTML?
- `ws_mail_pane_body_html()` (`mail_pane.php`): a mail with kept HTML gets `<iframe class="ws-mail-frame" src="workspace_mail_frame.php?item=<id>" loading="lazy">` with the mail mode's sandbox, a Plain text switch (`aria-pressed`) and its text hidden beside it. A mail without HTML, or a pane drawn without the panel's address, keeps the escaped text.
- The pane reads named columns (`ws_mail_pane_columns()`), never `body_html` (up to 1 MB each). `has_html` and `outside_pictures` (LOCATE of ` data-pg-src="http` or ` data-pg-background="http`) come from SQL; before the column exists both are 0.

## What does workspace_mail_frame.php answer?
- Only as a frame (`pg_guarded_frame_request_framed()`), to a Workspace member, for a mail of a conversation with its card whose channel the reader may read (`ws_mail_frame()`); else the same 404 line. `&pictures=1` asks for pictures from outside.
- `pg_mail_html_document()` wraps the cleaned HTML: `<base target="_blank">`, white in both themes (mail is written for white), html and body height auto so the shell measures the mail.
- The mail's own pictures are its `ws_messages` files (`sender_kind = 'mail'`, `sender_id` = the mail's id), at most 100, by file id.

## What is the guarded frame's mail mode?
- `pg_guarded_frame_kind('mail')`: the static mode plus `allow-popups allow-popups-to-escape-sandbox` (a link opens an ordinary new tab) and `img-src 'self' data:`, with `https: http:` only for `&pictures=1`. An address never asks for it: `pg_guarded_frame_mode()` gives only script or static.
- The shell's nonce script dresses the inert document once loaded (`pg_guarded_frame_mail_script()`): links get `target="_blank" rel="noopener noreferrer"` (a `#` link stays), `data-pg-file` pictures their kept file, and, with pictures asked for, `data-pg-src` and `data-pg-background` are put back unless on the site's own host. It runs in the browser, so nothing depends on the server's libxml.
- The height sent adds a sideways scroll bar's: a wide newsletter scrolls inside its frame.

## How does Show pictures work?
- "Pictures from outside are not shown." with Show pictures appears only for a mail whose HTML names outside pictures. Pressed, every frame of that sender loads its `&pictures=1` address, the line goes and focus moves to Plain text.
- Remembered per sender in localStorage `ws-mail-pictures:<api url>|<user id>`: lower-case addresses, newest first, at most 500. A pane drawn later, by the script or the server, loads them at once; a storage that fails loses only the memory. Owner decision: `workflow/decisions-made-workspace-17.md`.

## How does the height reach the pane?
- `screen()` in `workspace_mail.js` takes `{pg: 'guarded-frame', height}` only from this site's origin and from the window of one of the pane's `.ws-mail-frame`s; `frameHeight()` keeps it to whole pixels, 0 to 100000. A frame starts 12rem high.

## What changes in the list?
- With the pane open (`.ws-mail-reading`) a row's last line hides all but its Not sent and Waiting to be sent badges, and the columns are 1fr / 2fr (were 2fr / 3fr). Below 768 px the pane still takes the list's place.

## Tests?
- `tests/workspace_mail_frame_test.php` (pane body, columns, route, script under Node), `guarded_frame_test.php` (mail policy, sandboxes, shell), `mail_html_test.php` (the document). The Playwright proofs (`tests/js/guarded_frame_proofs.js`) were not extended; the browser check is in the PR.
