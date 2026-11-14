---
name: email-designs-ready
description: The five ready e-mail designs (MA5d #9525): page trees in code, Use this design, the previews on the Templates tab drawn as sent in the site's colours (MA5d2)
module: email_designs_ready
---

## Which ready designs are there?
- Five, in `pg_email_ready_designs()` (`includes/fn/email_designs_ready.php`): Newsletter, Sale or launch (two product cards in two columns), Announcement, Welcome e-mail and After an order (both greet with `^^first_name^^`).
- `pg_email_ready_design_tree($key)` builds each from the mail blocks only (`pg_mail_part_*()`, the PHP twins of the palette's parts in `style_designer_blocks_mail.js`): the frame, the Header first, the Footer last. So no check line shows on them, and a campaign from one gets its own footer filled (`email-designs-parts.md`).
- They are part of this plan, not design templates: code, not rows; never installed.

## How is one used?
- "Use this design" (role <= 1) opens `add_system_style.php?start=email&ready=<key>`: a new e-mail design as `?start=email` makes it (`email-designs.md`), its tab starting from that tree. An unknown key gives the plain starter.

## Where are they shown?
- Communication › Templates, the E-mail designs card (`pg_messages_template_email_card()`), in place of the old "Soon" row: each ready design with a preview, its name, line and button, and "Your e-mail designs", the pages of the e-mail designs folder, newest first, each opening its editor.
- Architect (channel 3238): with none of one's own the ready designs come first and a line says there are none; once there is one, "Your e-mail designs" stands first, then "Ready designs", and the card's line says so.
- A preview is `messages_templates.php?preview=<key>`: `pg_email_ready_design_html()` draws the tree as a page body and runs the mail pass, so it shows what a campaign would store. Its frame is sandboxed (no script), a 640 px frame (wider than the phone rule) drawn at 37.5 %, taking no click (owner 2709: a design choice shows a preview). On a phone it fills the tile at half size, 200 px high.
- The preview writes a sample recipient for merge fields (`pg_mail_preview_samples()`: Jane, Jane Doe, jane@example.com, translated; others left out); the design keeps the fields (channel 3238).

## In which colours?
- A new e-mail design (`?start=email`, a ready one or a copy) wears the site's palette, the one the home page's design wears (`pg_email_site_palette()`), unless `?palette=` is given; the previews are drawn in it too (MA5d2 #9542). The mail pass then writes that palette's colours (`email-designs.md`).
- Copies of the template's e-mail pages and of earlier campaigns' pages: `email-designs-copies.md`.

Test: `tests/email_designs_ready_test.php`, `tests/messages_templates_test.php`.

Related: docs/memory/designer/email-designs.md, docs/memory/designer/email-designs-copies.md, docs/memory/ui/messages-area-templates-tab.md
