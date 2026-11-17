---
name: mail-screen-phone
description: Communication › Incoming e-mail below 768 px: the pane in the list's place, the toolbar folded while a mail is read, the scroll to the pane, the narrower paddings (#10082)
---

## What does the screen do on a phone?
- Below 768 px (`backend.src.css`, the E-mails block's media rule) the reading pane takes the list's place once a conversation is open (`.ws-mail-reading`); Close brings the list back.
- While a mail is read the toolbar (`.ws-mail-reading > #button_bar.pg-toolbar`, the id outranking `#button_bar`'s own display: New e-mail, Check e-mail, the filters, the search, the views) and the Check e-mail line fold away. Before (#10082) they stood about 490 px above the pane and the mail began around 900 px down.
- `open()` in `workspace_mail.js` scrolls the screen's root into view on a first open when `phone()` (`matchMedia('(max-width: 767.98px)')`, the stylesheet's width) holds; a redraw after a change does not move the page.
- The pane and each mail have .5rem paddings there, so a mail is no longer about 260 px of a 375 px screen.

## What stays as it was?
- Before a conversation is opened the toolbar still stands above the list; folding it there was not part of #10082.

## Tests?
- `tests/workspace_mail_list_test.php` (`test_workspace_mail_pane_on_a_phone`: the media rule, the paddings, the scroll). Pictures at 390 px were taken on the sandbox for the PR.
