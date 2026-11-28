---
name: bug-hunt-areas-message-link-preview-translate
description: The bug hunt in a Workspace message's link preview and Translate (2026-11-28): what was fixed, what was read clean, what is left
---

## Which area was it?
- A Workspace message's link preview (`includes/workspace/link_preview.php`, its pass in `job.php`, the card and "Remove preview") and Translate (`assets/js/workspace_translate.js`).
- Asked by the architect (agent channel) as area `message-link-preview-translate`. In `messages.php` and `workspace.js` only the preview and translate parts were read.

## What was fixed?
- A page whose charset only its Content-Type header named, by an alias (Shift_JIS, gbk, big5, cp1252), got a garbled card: the name was looked up in `mb_list_encodings()` only. Now `ws_link_preview_charset()` asks mbstring itself.
- An address whose host is written in its own letters (`çiçek.com.tr`) never got a card: the host check took only ASCII names. Now it is checked and called in its xn-- form (`ws_link_preview_ascii_url()`).
- Closing rows that waited over a day stamped `read_at`, which the hourly cap of 60 counts: a backlog closed at once (the job off for a day) held every read for an hour. The close no longer stamps it.
- An edit to another address between the pass's read of its rows and its claim (a pass reads 5 rows, each up to 8 s) let the claim take the new row for the old address: skipped, then never read. The claim now names the address.
- A redirect chain had 8 s a hop, so one address could hold the job about 32 s past its 20-s pass. Now the 8 s cover the whole read.
- Translate: Georgian, Khmer, Ethiopic and other scripts outside a fixed list of ranges read as "nothing to translate". A letter is now `\p{L}`.
- Translate: a redraw of the message while the translation or the language download ran (a reaction, a sync) put the progress and the result on the detached old body; the reader saw nothing. They now go to the body on screen.

## What was read and found clean?
- First address: code, fences and tags skipped, sentence marks and unbalanced brackets cut, 2048 bytes.
- Refused addresses: credentials, ports, private and reserved addresses (a bracketed IPv6 literal never resolves in `api_http_check_url()`), local names, numeric last labels; each redirect checked again; only HTML; the 256-KB cut.
- The card: escaped where drawn, `javascript:` refused, only `shown` rows, nothing while the switch is off; "Remove preview" only for the writer, read access checked by the action.
- Translate: nothing sent or stored, tags, code, addresses kept, the translated text set as text, never as HTML.

## What is left?
- A message edited while its translation runs can show the translation of its old text until the next redraw.
- "Remove preview" while the switch is off answers "Only the person who wrote the message…" instead of saying previews are off.
- An address ending in "…" keeps the ellipsis in the card's link.
