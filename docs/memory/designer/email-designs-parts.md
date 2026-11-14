---
name: email-designs-parts
description: The E-mail group's parts in the palette (MA5c, MA5c2): what each holds, the footer filled when a campaign is stored, the greeting's merge field, the product card written by hand
---

## Which parts does the E-mail group offer?
- Ten, in `assets/js/style_designer_blocks_mail.js` (prefix `mail-`): Frame; Heading and Text; Text and Button; Picture and Text; Section Break; Header; Greeting; Product Card; Footer; Two Columns.
- Each draws at least three marks in its hover drawing (`tests/designer_block_sketch_test.php`), so a lone heading or divider is not a part of its own: the parts are small groups (`_mail: 'group'`) whose elements are edited or removed one by one.
- Header: the logo (`logo`, 160 px), one line and a divider. Greeting: "Hello ^^first_name^^," and two paragraphs; the field is filled per recipient at send by `replace_variables()`, the canvas shows the token.
- Product Card (`product`, `price`): picture, name, price and a button, all written by hand. A card bound to a product (its name, picture, price and address read at send) needs a server draw and a canvas sample: not built.

## How is the Footer filled?
- The block carries `data-pg-mail-footer`, so the mail pass reports it and the fixed campaign footer is left out (`campaign-email-body-mail-pass.md`).
- Its links point at `#pgmail-web`, `#pgmail-preferences` and `#pgmail-unsubscribe`; its address line carries `data-pg-mail-address`.
- After the body is prepared, `pg_mail_footer_fill()` (`includes/fn/email_designs.php`, pure) writes the web version (`view_email_campaign.php?r=<reference_code></reference_code>`) and the preferences page (`email_preferences.php?id=<email_address_id></email_address_id>`, also for unsubscribe) in place of any href ending in those anchors (a tracking code the preparation added goes with it).
- The address line gets the organization's address, escaped, from `pg_mail_footer_address()` (empty parts left out).

## What does the check line say?
- `_sdMailCheckIssues()` in the editor's validation: in an e-mail design, the outermost element that is no mail block and no tbody or tr gets "Not an e-mail block: …" (rule `mail_block`): its look comes from classes the mail pass takes out.

Test: `tests/email_designs_test.php`.

Related: docs/memory/designer/email-designs.md, docs/memory/designer/email-designs-editor.md
