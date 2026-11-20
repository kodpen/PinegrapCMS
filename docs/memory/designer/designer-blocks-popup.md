---
name: designer-blocks-popup
description: The Pop-ups blocks (popup- prefix, group Pop-ups): offer code with Copy, newsletter, announcement bar, cart waiting; the data-pg-popup-* marks the site's dialog script reads; rules, tests
---

## Where do they live?
- `assets/js/style_designer_blocks_popup.js`, a family file of its own ([[designer-block-family-files]]): prefix `popup-`, group "Pop-ups" (`bi-window-stack`). PR #10570; the feature as a whole: `platform/auto-dialog-pop-ups.md`.
- They are made to be the content of a designed auto dialog: a shared component designed alone, chosen under "What does it show?" on the Edit Auto Dialog screen (`platform/auto-dialog-content.md`). Nothing stops their use on a page; there the marks do nothing.

## What does each block draw?
- `popup-offer` (Pop-up: Offer Code): a gift icon, heading, line, the code `WELCOME10` in a dashed frame (`span.pg-popup-code`, `data-pg-popup-code`), a Copy button, a "No thanks" button. For a centred pop-up.
- `popup-newsletter` (Pop-up: Newsletter): heading, line, a `<form method="post">` with a hidden label, an email box and Subscribe, "Not now". Like the core Newsletter blocks, the form posts to the page; the operator binds or replaces it.
- `popup-announcement` (Pop-up: Announcement Bar): one line on `text-bg-primary`, a "Shop now" link and a white cross at the end. For place top or bottom.
- `popup-cart` (Pop-up: Cart Waiting): a small card, "Your cart is waiting", a "Back to my cart" link. For audience cart and place corner. Its link is `#`: no binding gives the cart page's address, so the operator sets it.

## Which marks does the dialog script read?
- `data-pg-popup-close` on every close button: the cross (`btn-close`, `aria-label` Close, top right of the `position-relative` root) and the "No thanks" / "Not now" button. Content holding such a button leaves out the dialog's own close button (`pg-dialog-own-close`).
- `data-pg-popup-take` on exactly one main action: Copy, Subscribe (a submit button: the form's submit counts too, the beacon is sent once), Shop now, Back to my cart.
- `data-pg-popup-copy` on Copy, with `data-pg-popup-copied` ("Copied", translated in the designer) and a label span marked `data-pg-popup-copy-label` (`aria-live="polite"`). `software.popup_copy()` copies the text of the pop-up's `[data-pg-popup-code]` and shows the copied text for 2 s (`platform/auto-dialog-content.md`).

## What did the bug hunt read?
- The pop-up hunt of agent channel message 10243 read Copy (Clipboard API, else a selection and `execCommand('copy')`; the code stays selected when both fail) and the newsletter form clean. The form is markup only and posts to its page, so its CSRF token and rate limit belong to what the operator binds it to.

## Which rules do they bring?
- `pg-popup-card` (`max-width: 28rem`; offer, newsletter, cart): the dialog sizes designed content to it.
- `pg-popup-code` (offer): `--bs-border-style: dashed`, read by Bootstrap's `.border`, and letter spacing. No inline style: icon sizes are `fs-*` utilities.

## Tested?
- `tests/auto_dialog_popup_blocks_test.php`: group and entries under Node, the marks per block, the offer's code and Copy, the printed markup (`_render_tree_node()`), the rules, the script in `frontend.src.js` and `frontend.min.js`. `tests/designer_blocks_test.php` checks the file like every family.
- An `id` in `_attrs` is never printed on the site (`_render_extra_attrs()`): the email box's id is its node's `id` prop, so its label points at it.
- Not seen in a browser: the blocks inside a real dialog, the copy on a phone. Pictures to the owner were waived (agent channel message 9546).
