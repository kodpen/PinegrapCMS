---
name: campaign-email-body-way
description: MA4 proposal (2026-11-13, open on #9083): a campaign's e-mail body as an e-mail design page (600 px canvas, mail blocks), the mail pass, ready designs, pasted HTML, test send
---

## Is this settled?
- No: step MA4's proposal of 2026-11-13, waiting for the architect on #9083. MA5 (after MA3) builds what the answer keeps. Facts: [[campaign-email-body-today]], rules: [[campaign-email-body-mail-programs]].

## What would an e-mail design be?
- A page, as today's e-mail pages: in a private folder "E-mail designs" made on first use (its id in `config_text` `email_designs_folder_id`, `data/config-table-no-varchar.md`), no search, no sitemap, noindex. No schema.
- Its root carries `props.pgEmail` (tree data): the designer opens it with the canvas 600 px wide, a Phone view at 375, the E-mail state on and the mail blocks in the palette. A campaign's body page is one of these, or any page as today.
- Mail blocks: frame, header, heading, text, button (a link), picture, divider, space, two columns, product card (bound to a product: name, picture, price, link), footer (bound to the web version, address and unsubscribe), merge fields as tokens.
- Each mail block writes its look inline when drawn, as the templates' `$mail_style` does, with colours resolved to hex; classes stay for the canvas. So no CSS inliner is needed, and a block from the ordinary palette inside an e-mail design gets a check line instead.

## What would a send take from it?
- A pure mail pass after the e-mail render: stylesheet links and page CSS out, one small head style in (phone query, dark mode), the frame and column rows as presentation tables, `var(--x,#hex)` written as the hex, the fixed footer left out when the design's footer block is there, a warning over 102 KB.
- The result is stored in `email_campaigns.body` with `page_id` at each save of the campaign, as today: what was previewed and tested is what goes. The content step says so when the design changed after the stored body, with a button to take it again.

## Which ready designs would there be?
- Five in code, built as template page trees: Newsletter, Sale or launch (with product cards), Announcement, Welcome, After an order. "Start from a ready design" copies one into a new e-mail design in the site's colours.
- The installed template's own e-mail pages and earlier campaigns' designs are offered beside them, each as a copy.
- The body area of a ready design is marked `_editable` and the header and footer are shared components, so a manager or a role 3 campaign maker fills the body at content level.

## What would happen to pasted HTML?
- It stays its own body and is never turned into designer nodes: mail HTML from other tools is tables and inline styles, and the importer drops a paste's `<style>` and rebuilds the markup (`html-import-and-paste.md`).
- Stored as the body with `page_id` 0 and format html, edited in the code editor. Cleaned at save by an outgoing mode of the incoming-mail allowlist (`includes/fn/mail_html.php`): its element and attribute lists (no scripts, forms, frames, event attributes, `javascript:`), but `@media` rules, https pictures and https `url()` kept, which the incoming mode refuses or holds aside. The footer is added as today.

## How would preview and test be made?
- Wide and phone preview of the stored body in the guarded frame's mail mode (`workspace/mail-html-preview.md`, `pg_mail_html_document()`): no scripts, pictures shown, light and dark.
- "Send a test to me": the stored body to the signed-in user's address and up to five typed ones, subject marked as a test, merge fields from the user's contact or sample values, through `email()` with type campaign so the campaign mail server is the one tested; never counted in the campaign.

## What would stay out?
- No table and no column. Open and click tracking stays out (`platform/messages-area-census-sends.md`).
- Campaign profiles keep drawing their page at each send until MA9 turns them into flows.
