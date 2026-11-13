---
name: mail-screen-pane-wrap
description: Long file names and addresses without spaces wrap inside a mail of the E-mails reading pane (text-break on each mail, #9009)
---

## Why does a long attachment name not widen the pane?
- `ws_mail_pane_item_html()` (mail_pane.php) gives each `<article class="ws-mail-item …">` the `text-break` utility, so a file name, a "Not taken in" name or an address without spaces breaks inside the mail, as the mail text does (`.ws-mail-text`, `overflow-wrap: anywhere`).
- Symptom fixed (#9009): a 300-character attachment name made the pane scroll sideways (1,582 px of content in a 370 px pane on a phone), taking the mail's buttons and text with it.
- A rule on `.ws-mail-item` in backend.src.css would do the same; the class was chosen while another PR held that file.

Tests: `tests/workspace_mail_list_test.php` (the article's classes).
