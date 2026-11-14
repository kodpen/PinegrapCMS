---
name: email-designs
description: E-mail designs (MA5c): the private folder, props.pgEmail, the 600 px canvas and Phone view, mail blocks as presentation tables, their inline look map and canvas twin
module: email_designs
---

## What is an e-mail design?
- A page of a visual design whose root carries `props.pgEmail` (`pg_email_design_tree_is()`, `includes/fn/email_designs.php`). It is made with "New e-mail design" on New campaign (role <= 1) or `add_system_style.php?start=email`: one unsaved tab with the starter tree (frame, heading, text, button), the folder set, no search, no sitemap, noindex, nofollow, never a draft.
- Its folder: the private "E-mail designs" folder, made on first use; the id is `config_text` `email_designs_folder_id` (`pg_email_designs_folder_id($create)`). A folder deleted or in the bin is replaced by a new one.

## How does the designer show it?
- `_sdEmailCanvasSync()` (called after every `renderCanvas()` in `render()`) sets `.sd-canvas-email` on `#sd-canvas`: the page widths hide and two buttons show, "E-mail" (600 px, chosen on open) and "Phone view" (375 px). A page that is no e-mail goes back to full width.
- The palette's E-mail group (family file `assets/js/style_designer_blocks_mail.js`, prefix `mail-`) shows only in an e-mail design, first; elsewhere it is left out (`_sdBlockPaletteGroups()`).

## What is a mail block?
- A semantic node with `props._mail` naming its kind: frame, cell, group, heading, text, button_row, button, picture, divider, space, columns, column. The tree keeps classes and props only.
- The palette offers six parts, each at least three marks in its hover drawing (`tests/designer_block_sketch_test.php`): Frame; Heading and Text; Text and Button; Picture and Text; Section Break; Two Columns. A part other than the frame and columns is a `group` div.
- The frame (`table.pg-mail-frame`, 600, centred) and Two columns (`td.pg-mail-col`, `width="50%"` as an attribute, not inline, so a phone rule overrides it without `!important`, which family CSS may not use) are `role="presentation"` tables with width attributes, as Outlook's Word engine needs; the mail pass's head style makes them fit a phone. The pass itself writes no tables.
- The family's own CSS has the same phone rules, so the canvas's Phone view stacks the columns; the starter tree built in PHP carries no block marker, so its frame gets that canvas rule only once a Frame block is on the page.
- Its inline look comes from `pg_mail_block_styles()` when drawn: `pg_mail_block_inline()` puts it in front of the node's own style in `_render_tree_node()`'s semantic case, so a style set in the designer wins. The canvas twin is `_SD_MAIL_STYLES` / `_sdMailInline()` (canvas, table cells and the editor's HTML); `tests/email_designs_test.php` compares the two maps line by line.
- Colours are `var(--bs-…,#hex)`: the canvas follows the design, the mail pass writes the hex. Fonts are Arial, Helvetica, sans-serif.

## What does a campaign store from it?
- `pg_email_campaign_design_body()` (`includes/fn/email_campaign_create.php`): the page's e-mail render; for an e-mail design then `pg_mail_pass()`, and the fixed footer only when the design has no block marked `data-pg-mail-footer`. Any other page is stored as before (no pass).

## What is left for MA5c2?
- The parts not built here: header, footer (web version, address, unsubscribe; `data-pg-mail-footer`), product card, merge fields as tokens, the check line for an ordinary palette block inside an e-mail design (`plans/messages-area-steps.md`).

Test: `tests/email_designs_test.php`.

Related: docs/memory/designer/campaign-email-body-way.md, docs/memory/designer/campaign-email-body-mail-pass.md, docs/memory/ui/campaign-content-step.md
