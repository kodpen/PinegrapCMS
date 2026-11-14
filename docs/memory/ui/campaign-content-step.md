---
name: campaign-content-step
description: The content card of New campaign and Edit Campaign (MA5b #9426): body as a design, pasted HTML or plain text, the preview and phone preview, "Take the design again", what is stored
module: campaign_content
---

## What does the content card hold?
- `pg_campaign_content_card()` (`includes/fn/campaign_content.php`) replaces the old E-Mail Message card on `add_email_campaign.php` and `edit_email_campaign.php`, drawn from `plans/messages-area/messages-area.html`: subject and the mail on the left, how the body is made on the right (one column under 992 px, the body part first).
- Body kinds: Design (a page drawn as an e-mail), Paste HTML, Plain text. On New campaign a segmented control chooses; after create the stored kind is shown, never changed (`pg_campaign_content_kind()`: plain_text is text, html with a page a design, html with page_id 0 pasted).
- The kind radios and the phone switch sit before the card's grid, so CSS shows the chosen kind's parts; the only script makes the frame and "Edit the design" follow the picked page (`pg_campaign_content_script()`).
- Page is the page picker field, kind `page_view`, as the campaign profile's Page (architect, channel message 2647; `picker-page-fields-screens.md`); no list of every page is read. Its hidden input (`data-pg-campaign-page`) fires change; the frame's address is `data-base` plus the picked name, "Edit the design" is `edit_page.php?id=`, which forwards a designer page and refuses one the user may not edit.

## What is stored for each kind?
- Design: the page's e-mail render through `pg_email_campaign_design_body()` (an e-mail design through the mail pass first, `designer/email-designs.md`) and `pg_email_campaign_prepare_html_body()` (`core/email-campaign-html-body.md`), page_id kept; the save still checks view access.
- On New campaign a role <= 1 user also gets "New e-mail design" under Page: it opens `add_system_style.php?start=email` in a new window.
- Pasted HTML: `pg_mail_html_clean_outgoing()` (`designer/campaign-email-body-mail-pass.md`), then the same preparation with the subject as tracking name; page_id 0. Nothing left after cleaning stops with "Paste the HTML of the e-mail: nothing that can be sent was left of it." Edit shows it without the fixed footer (`pg_campaign_content_without_footer()`) and cleans it again at save, so the footer stays single.
- Plain text: as before.

## What does Edit Campaign add for a design?
- When the page changed after the campaign's last save (`page.page_timestamp` over `last_modified_timestamp`, manual campaigns only): an amber line and "Take the design again", which draws the page anew (view access checked), saves, and stays on the screen with a notice. The stored e-mail is what goes.

## How is it previewed?
- A frame of the page (create, `?edit=no&email=true`) or of the stored body (`view_email_campaign.php?id=`); "Preview on a phone" narrows it to 375 px.

## What is known and left?
- A page's header can put the sender's account bar (name, logout link) into the stored body: a widget outside the signed-out e-mail render (`core/email-campaign-html-body.md`), seen on the sandbox's home-1; the e-mail designs of MA5c draw no such header.
- Ready designs (MA5d) and "Send a test to me" (MA5e) come later; the recipients, sender and schedule cards are still the old ones (DL5).

Test: `tests/campaign_content_test.php`.
