---
name: campaign-email-body-mail-pass
description: The mail pass for designed campaign bodies and the outgoing mode of mail_html.php for pasted HTML (MA5a #9413): what each keeps, takes out and reports
module: mail_pass, mail_html
---

## What does the mail pass do?
- `pg_mail_pass($html)` (`includes/fn/mail_pass.php`, pure) runs on the e-mail render of an e-mail design before the body is stored. It takes out scripts, every `<style>` (the page CSS) and every `<link>`: mail programs drop linked sheets and most read no class rules, so a design carries its look inline.
- Every `var(--x, fallback)` becomes its fallback, the innermost first; a `var()` without one stays. No mail program knows the page's custom properties.
- One head style goes in (`pg_mail_pass_head_style()`): under 620 px `.pg-mail-frame` takes the width, `.pg-mail-col` stacks, `.pg-mail-wide-only` hides. A program that drops it still shows the inline look.
- It answers `html`, `has_footer` (the design has a block marked `data-pg-mail-footer`: the fixed campaign footer is then left out), `bytes` and `clipped` (over 102 KB, where Gmail cuts a mail, `pg_mail_pass_clip_bytes()`).
- It writes no tables: the mail blocks' frame and columns are presentation tables already (`email-designs.md`). It runs only on an e-mail design's body (`pg_email_campaign_design_body()`).

## What does the outgoing mode keep?
- `pg_mail_html_clean_outgoing($html)` cleans HTML pasted as a campaign's body with the incoming lists (`pg_mail_html_elements()`): no scripts, forms, frames, event attributes, `javascript:` links, `@import` or `@font-face`.
- Unlike incoming it answers a whole document (viewport meta, the sheet with its `@media` rules in the head, the body's look on `<body>`), keeps an https picture's `src` in place and https `url()` in styles, and keeps a link that is one merge field (`^^name^^`).
- Plain http pictures, Content-ID pictures and `data:` urls in CSS are left out. Over `pg_mail_html_max_bytes()` (1 MB) before or after, or nothing to read left: ''.
- The incoming mode is unchanged: both share `pg_mail_html_read()`, the mode passed down to the CSS, link and picture checks.

Tests: `tests/mail_pass_test.php`, `tests/mail_html_outgoing_test.php`.

Related: docs/memory/designer/campaign-email-body-way.md
