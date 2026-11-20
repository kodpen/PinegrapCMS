---
name: mail-screen-phone
description: Communication › Incoming e-mail below 768 px: the pane in the list's place, the toolbar folded while a mail is read, the scroll to the pane, the narrower paddings (#10082), the channel pill on touch
---

## What does the screen do on a phone?
- Below 768 px (`backend.src.css`, the E-mails block's media rule) the reading pane takes the list's place once a conversation is open (`.ws-mail-reading`); Close brings the list back.
- While a mail is read the toolbar (`.ws-mail-reading > #button_bar.pg-toolbar`, the id outranking `#button_bar`'s own display: New e-mail, Check e-mail, the filters, the search, the views) and the Check e-mail line fold away. Before (#10082) they stood about 490 px above the pane and the mail began around 900 px down.
- `open()` in `workspace_mail.js` scrolls the screen's root into view on a first open when `phone()` (`matchMedia('(max-width: 767.98px)')`, the stylesheet's width) holds; a redraw after a change does not move the page.
- The pane and each mail have .5rem paddings there, so a mail is no longer about 260 px of a 375 px screen.

## Why do the views wrap?
- The views (Incoming, Sent, Not sent, Blocked e-mail, Drafts) are a `btn-group … flex-wrap` (`ws_mail_list_filters_html()`, #10543). The Workspace toolbar's rule keeps a group on one line, scrolling sideways with no scrollbar: at 390 px the group was 619 px in 358 and Blocked e-mail and Drafts lay out of sight.

## How high are the pane's status and holder?
- 31 px, as the pane's buttons: `state(…, roomy)` in `workspace_mail.js` leaves out `py-0` for the pane (#10543); before they were 23 px. A channel's mail card keeps `py-0`.

## How big is the channel pill in the pane's head?
- On a touch screen (`@media (pointer: coarse)` after the E-mails phone block) "In #channel" (`.ws-mail-pane-channel`) is at least 2rem (32 px) high; under a mouse the 22 px status pill stays (#10545, PR #10592). Test: `tests/workspace_phone_touch_test.php`.

## What stays as it was?
- Before a conversation is opened the toolbar still stands above the list; folding it there was not part of #10082.

## Tests?
- `tests/workspace_mail_list_test.php` (`test_workspace_mail_pane_on_a_phone`: the media rule, the paddings, the scroll); `tests/mail_phone_width_test.php` (the views, the selects). Pictures at 390 px were taken on the sandbox for the PR.
