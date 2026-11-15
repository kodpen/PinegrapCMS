---
name: comment-email-pages-system-region
description: Comment e-mail pages: the design templates ship them without a System region (the card always draws), the canvas note on a page that keeps one, the fixed markup kept
---

## What does the System region of a comment e-mail page print?
- The fixed comment markup of `mail.php` (`$extra_system_content`, `send_comment_email_to_custom_form_submitter()`, `send_comment_email_to_watchers()`): who wrote it, when, its words, its file and a View or Reply link.
- `get_page_content.php` puts it into the page's own (unnamed) System region unless a Comments widget on the page drew the comment and claimed the page (`pg_comments_email_keeps_fixed_markup()`, [[comments-widget-email]]). A page with no region drops it.

## Why do the design templates have no System region on their comment e-mails?
- Every template's comment e-mail card always draws: it keeps its link to the comment (`pg_comments_email_drew()`) and every page naming the e-mail holds its own thread or a record's, never a product page (where `pg_comments_email_decision()` refuses an unverified review). The region printed nothing there.
- 57 templates (the 56 shipped ones and bikeshop's requests part), each shipped one a patch version up. Installed sites keep their pages.
- Kept for a page with no card: the fixed markup and its code in `mail.php`, unchanged; nothing changes on a running site.
- Checked by `tests/comments_widget_email_pages_test.php` (every template: no product page names the e-mail, the card draws with its link, no System region) and the templates' own tests.

## What does the canvas show on a page that keeps the region?
- Under the page's own System region label, `_sdAppendSystemRegionEmailNote()` adds "On this comment e-mail it prints the comment and its View or Reply link when no Comments card on the page draws it." (`.sd-region-email-note`, a dark-mode colour of its own).
- Shown when an open page names the active page as its comment e-mail page (`_sdCommentsEmailPageOf()`: `pg_comments_email_page` equal to its id, or `tab:<key>` before the first save). The design's pages are all open tabs; a classic page naming it is not seen.
- Editor chrome inside `.sd-region-ph`, which the canvas checks skip.

## What stays closed?
- A user-made card that may refuse (a product page's reviews naming the e-mail, a card without the link): the region and its fixed markup stay their fallback; the E-mail state note says what the card needs ([[comments-widget-email-design]]).
