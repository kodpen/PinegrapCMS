---
name: design-template-mail-frame-light
description: Every design template's E-mail Frame is data-bs-theme="light" so the 600 px E-mail Card stays light under a dark page theme; the test over all templates (#10070).
---

## Why is the E-mail Frame `data-bs-theme="light"` in every template?
- The E-mail Card carries `bg-body` (`!important`) beside its inline `background:#ffffff`, and the text colours are written inline for a light card. Under a dark page theme (canvas Dark view, or a browser set to dark with the design on Auto) the card turned dark under the dark text: 1.05–1.15:1 on the store's order receipt e-mail (#10070).
- A sent e-mail carries no theme, so the fault showed only on the canvas and when the page was opened in a browser.

## Where is it set?
- On the frame `div` (label E-mail Frame) built by each template's `$mail_page` helper (`$fin_mail_page` in the dealership's `finance_requests.php`), first in its `_attrs`, before the inline `style`. There is no shared builder: each template defines its own `$el` and `$attr` closures, so each helper carries the attribute.
- Forum, magazine and news had it already; the other templates (hello, store, bookshop, boutique, cafe, clinic, playground and the folder templates' `mail.php`, events and the unlisted bike shop too) got it in #10070, with each template's patch version raised.

## What changes in the stored page and the sent mail?
- The frame's `<div>` gains one attribute, `data-bs-theme="light"`; nothing else in the page HTML moves. An e-mail program ignores it, so the sent mail looks the same.

## How is it checked?
- `tests/design_template_mail_card_light_test.php` loads every template, finds each card (inline style starting `max-width:600px` with class `bg-body`) and asserts its nearest `data-bs-theme` ancestor is `light`. A new template's e-mail helper must keep the frame light.
