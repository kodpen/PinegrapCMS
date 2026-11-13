---
name: mail-html
description: A mail's HTML is kept (WM11, PR #8821): ws_mail_items.body_html, the allowlist cleaner, cid pictures kept as files, the fill of earlier mail from its .eml
module: mail_html
---

## Where is a mail's HTML kept?
- `ws_mail_items.body_html` MEDIUMTEXT NULL: the HTML part after `pg_mail_html_clean()` (`includes/fn/mail_html.php`). NULL: no HTML part, nothing left to read, or over 1 MB (`pg_mail_html_max_bytes()`) before or after cleaning. `body_text` stays what search, the card and the answer quote read.
- Step `upgrade_2026_11_0_ws_mail_html()` (11.0.188) also widens `ws_channels.kind` by `mailbox` (the room of a box kept in no channel, WM13), only when the value is missing. Owner approved it 2026-11-11 (#8765).

## What does the cleaner keep?
- It rebuilds the HTML element by element (DOMDocument; the mail's own charset meta tags are taken out first, the text is already UTF-8). Kept: text, headings, lists, tables, `font`, `center`, `a`, `img`, with the attributes of `pg_mail_html_elements()` plus the common ones (id, class, style, title, lang, dir, align, valign, width, height, bgcolor, role, aria).
- Left out with their content: script, iframe, frame, object, embed, form controls, svg, math, media, template, base, meta, link, the head. An element on no list (`<form>`) is unwrapped, its text kept. Prefixed tags (`<o:p>`, `<v:shape>`) go before parsing (`pg_mail_html_unprefix()`): libxml 2.9 reads `<o:p>` as `<p>`. Text the HTML 4 parser hands to `<wbr>`, `<embed>` or `<source>` is given back.
- A link keeps only http(s), mailto, tel or `#`. The body's look (bgcolor, style, class, background) goes on a wrapping `div`.

## What can its CSS do?
- Inline styles and the mail's `<style>` sheets (one `<style>` first) keep only declarations passing `pg_mail_html_css_ok()`: no url (data: too), image-set, cross-fade, element(), image(), src(), attr(), expression, javascript, behavior, binding, escapes, comments, `@`, `<` or braces.
- A sheet keeps plain rules and @media / @supports three deep; @import, @font-face, @keyframes and every other at-rule are left out. The kept HTML fetches nothing by itself; the pane shows it in the guarded frame, which holds `position: fixed` and the like (`mail-html-preview.md`).

## How are pictures named?
- An outside address in `src` or `background` moves to `data-pg-src` / `data-pg-background`, so nothing loads (WM12's "Show pictures" puts it back). `cid:x` becomes `data-pg-cid` (`pg_mail_html_cid()`: decoded, small letters, plain characters only). A `data:image/png|jpeg|gif|webp;base64` src stays; any other `img` is left out.
- At ingest a picture the HTML shows by `cid:` is kept as the mail's file even without a name: `ws_mail_attachments_sort(..., $cids)` names it `image-N.png|jpg|gif|webp` (never SVG). Its file id is written as `data-pg-file` (`pg_mail_html_link_files()`).

## When is it written?
- `ws_mail_ingest()` cleans only when `ws_mail_html_ready()` (the cleaner and `pg_schema_has('ws_mail_items', 'body_html')`): the code lands before the upgrade runs. `body_html` goes in an UPDATE of its own, so a failed 1 MB write never takes the conversation, message or `raw_path` with it.

## How does earlier mail get its HTML?
- `ws_mail_html_fill()` from `workspace_recurring_job.php`, before the `.eml` sweep: 200 mails or about 20 s a run, incoming mail with a `raw_path` and NULL `body_html`, ids up to MAX(id) at its first run. Its place is config_text `ws_mail_html_fill` (JSON ceiling, cursor, done); done stops it for good.
- `.eml` files live 30 days, so older mail stays text only. Pictures are matched to the files the mail already brought by kept name, two of one name in turn (`ws_mail_html_fill_files()`); no file or message is added.

## Tests?
- `tests/mail_html_test.php`, `workspace_mail_ingest_test.php` (cid sort, fill matching), `workspace_mail_html_fill_test.php` and `workspace_mail_html_schema_test.php` (child process, fake db, run twice).
