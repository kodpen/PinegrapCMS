---
name: mail-screen-pane-wrap
description: Long subjects, file names and addresses without spaces wrap inside the Incoming e-mail reading pane (text-break on the head's subject and customer line #10496, on each mail #9009)
---

## Why does a long subject not run off a phone screen?
- `ws_mail_pane_html()` (mail_pane.php) gives the head's subject (`<h2 class="h5 mb-1 text-break">`) and its muted customer line ("name <address> · box") the `text-break` utility (#10496).
- Symptom fixed: a subject with a run of no spaces (an order number, a link) stayed on one line wider than the pane; the pane (`overflow: auto`) scrolled sideways and the subject's end sat past a phone's screen. A long customer address did the same.
- Cause: the head had no break rule at all; it was no flex or grid sizing fault (`.ws-shell-main` has `min-width: 0`, the columns are `minmax(0, …)`), and a subject with spaces already wrapped.
- The channel pill (`nowrap`, ellipsis, `mail-pane-channel-pill.md`) and the head's buttons (`.btn` is `nowrap`) keep one line; only the two text lines carry the class, so the desktop look is unchanged.

## Why does a long attachment name not widen the pane?
- `ws_mail_pane_item_html()` gives each `<article class="ws-mail-item …">` the `text-break` utility, so a file name, a "Not taken in" name or an address without spaces breaks inside the mail, as the mail text does (`.ws-mail-text`, `overflow-wrap: anywhere`).
- Symptom fixed (#9009): a 300-character attachment name made the pane scroll sideways (1,582 px of content in a 370 px pane on a phone), taking the mail's buttons and text with it.
- A rule in backend.src.css would do the same; the class keeps the head and the mails on one mechanism and needs no bundle.

## What about the list row?
- A row's subject and customer (`.ws-mail-subject`, `.ws-mail-who`) are cut on one line with an ellipsis by design (`min-width: 0`, `nowrap`, `overflow: hidden`, `mail-list.md`); they never ran off the row and were left as they are.

Tests: `tests/workspace_mail_list_test.php` (`test_workspace_mail_pane_html`: the head's and the article's classes). No phone-width browser run was made for #10496.
