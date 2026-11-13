---
name: campaign-email-body-today
description: MA4 census (2026-11-13): how a campaign's HTML body and the designed e-mails (account, form, comment, receipt) are made today, what the e-mail render leaves in, and who may design one
---

## How is a campaign's HTML body made today?
- Create Campaign, Format HTML: a page is picked (any page whose folder the user may view, `check_view_access()`). `pg_email_campaign_page_body()` draws it with `get_page_content(..., 'preview', $email = true)` and cuts the sender's session token (`core/email-campaign-html-body.md`).
- Then `add_email_campaign.php` adds the visitor tracking code (`t=`) to on-site links (not to a `^^field^^` address), puts the fixed footer before `</body>` (View this email at our site, the organization's address, Update email preferences or unsubscribe) and stores the result in `email_campaigns.body` beside `page_id`.
- The stored body is a snapshot: a later change to the page never reaches the campaign. Edit Campaign does not draw the page again.
- A campaign profile and the order, form, gift card and comment e-mails draw their page at each send (`includes/fn/mail.php`, `includes/fn/order_completion.php`), with the record in `$data` or the render context.
- Merge fields are `^^field^^` tokens filled per recipient by `replace_variables()`; `<reference_code>` and `<email_address_id>` fill the web version and preferences links.
- `email()` sends HTML with a plain text part made from it (`convert_html_to_text()`) and, for a commercial campaign, the List-Unsubscribe headers.

## What does the e-mail render leave in?
- The whole document: the head with the design's stylesheet links (Bootstrap, the theme, page CSS in `<style id="pg-page-css">`) and the body classes. Gmail and most webmail drop `<link>` stylesheets, so a page styled by classes arrives close to unstyled.
- Left out: scripts, the session token, the visitor-only injections (`$email` checks in `get_page_content.php`). Responsive image markup is stripped and pictures point at absolute addresses of the original bytes (`pg_img_strip_responsive()`, `pg_image_mail_original_urls()`). The language is written on `<html>`.
- No CSS inliner exists anywhere in the code.

## How are the designed e-mails made today?
- Account e-mails (welcome, password reset), form, comment and order receipt e-mails are pages too (`account-email-pages.md`, `design-templates-standard-pages.md`): in a private folder, no search, no sitemap, noindex.
- The templates build them with a `$mail_page` helper (`includes/design_templates/pinegrap-magazine.php` and others): a light frame (`data-bs-theme="light"`), a card `max-width:600px`, shared `email_header` and `email_footer` components, every look also written inline, colours as `var(--bs-primary,#hex)` so a client without the stylesheet keeps the hex, buttons as links.
- The canvas knows an e-mail only through the comments widget's E-mail state (`comments-widget-email-design.md`): there is no e-mail width, palette or check.

## Who may design one?
- The visual designer's levels (`includes/designer_access.php`): administrator and designer full; manager and user at content level, which writes text, a picture's address and the inside of blocks marked `_editable` (`designer-content-roles-save-gate.md`).
- So a role 3 campaign maker can fill a design only where a designer marked it; today they can only pick a finished page.

Related: [[campaign-email-body-mail-programs]], [[campaign-email-body-way]]
