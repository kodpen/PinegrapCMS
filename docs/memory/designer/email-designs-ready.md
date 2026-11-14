---
name: email-designs-ready
description: The five ready e-mail designs (MA5d): page trees in code, Use this design, the previews on the Templates tab drawn as sent, what MA5d2 adds
module: email_designs_ready
---

## Which ready designs are there?
- Five, in `pg_email_ready_designs()` (`includes/fn/email_designs_ready.php`): Newsletter, Sale or launch (two product cards in two columns), Announcement, Welcome and After an order (both greet with `^^first_name^^`).
- `pg_email_ready_design_tree($key)` builds each from the mail blocks only (`pg_mail_part_*()`, the PHP twins of the palette's parts in `style_designer_blocks_mail.js`): the frame, the Header first, the Footer last. So no check line shows on them, and a campaign from one gets its own footer filled (`email-designs-parts.md`).
- They are part of this plan, not design templates: code, not rows; never installed.

## How is one used?
- "Use this design" (role <= 1) opens `add_system_style.php?start=email&ready=<key>`: a new e-mail design as `?start=email` makes it (`email-designs.md`), its tab starting from that tree. An unknown key gives the plain starter.

## Where are they shown?
- Communication › Templates, the E-mail designs card (`pg_messages_template_email_card()`), in place of the old "Soon" row: each ready design with a preview, its name, line and button; then "Your e-mail designs", the pages of the e-mail designs folder, newest first, each opening its editor.
- A preview is `messages_templates.php?preview=<key>`: `pg_email_ready_design_html()` draws the tree as a page body and runs the mail pass, so it shows what a campaign would store. Its frame is sandboxed (no script), 600 px drawn at 40 % and takes no click (owner 2709: a design choice shows a preview).

## What does MA5d2 add?
- The installed template's e-mail pages and earlier campaigns' designs offered as copies, and a ready design in the site's colours (the mail pass writes Bootstrap's hex for `var(--bs-primary,…)` today).

Test: `tests/email_designs_ready_test.php`, `tests/messages_templates_test.php`.

Related: docs/memory/designer/email-designs.md, docs/memory/ui/messages-area-templates-tab.md
