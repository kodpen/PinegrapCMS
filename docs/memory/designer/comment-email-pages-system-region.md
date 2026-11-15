---
name: comment-email-pages-system-region
description: Comment e-mail pages: what their System region prints, the canvas note saying so on a page that keeps it, why the design templates keep it, the fixed markup kept
---

## What does the System region of a comment e-mail page print?
- The fixed comment markup of `mail.php` (`$extra_system_content`, `send_comment_email_to_custom_form_submitter()`, `send_comment_email_to_watchers()`): who wrote it, when, its words, its file and a View or Reply link.
- `get_page_content.php` puts it into the page's own (unnamed) System region unless a Comments widget on the page drew the comment and claimed the page (`pg_comments_email_keeps_fixed_markup()`, [[comments-widget-email]]). A page with no region drops it.

## What does the canvas show of it?
- Under the page's own System region label, `_sdAppendSystemRegionEmailNote()` adds "On this comment e-mail it prints the comment and its View or Reply link when no Comments card on the page draws it." (`.sd-region-email-note`, a dark-mode colour of its own). Before, the region was a bare label box.
- Shown when an open page names the active page as its comment e-mail page (`_sdCommentsEmailPageOf()`: `pg_comments_email_page` equal to its id, or `tab:<key>` before the first save). A design's pages are all open tabs; a classic page naming it is not seen.
- Editor chrome inside `.sd-region-ph`, which the canvas checks skip.

## Why do the design templates keep the region?
- Owner (2026-11-01): a comment e-mail page gets the widget's card and keeps its System region (`workflow/decisions-made-product-16.md` (a)). The architect kept it for CB3 (channel 25, message 4316), with #9685.
- Every template card draws the comment today (its link to the comment, no product page names the e-mail), so the region prints nothing there; it is the fallback when a user later points a product page's reviews at the e-mail (an unverified review is refused by `pg_comments_email_decision()`) or edits the card's link away.
- `tests/comments_widget_email_pages_test.php` checks all of it on every template: no product page names the e-mail, the card drawn as an e-mail keeps its link, the region stays.

## What did not change?
- The fixed markup and its code (`mail.php`, `get_page_content.php`): nothing changes on a running site.
