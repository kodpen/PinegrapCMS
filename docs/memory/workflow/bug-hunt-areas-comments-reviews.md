---
name: bug-hunt-areas-comments-reviews
description: Bug hunt 23, page comments and product reviews (2026-11-24): links in a comment's text, a deleted page's comment notifications, the deleted product finding, what read clean
module: comments
---

## What was the area?
- Page comments and product reviews from the visitor's post to the moderator: `add_comment.php`, `includes/fn/comments.php`, `convert_text_to_html()` in `includes/fn/mail.php`, the comment mails (`send_comment_email_to_*`), `job.php`'s scheduled publish, the classic block in `get_page_content.php`, the recent comments widget's audience, the Comments list cells, site search, and the page delete paths (`edit_page.php`, `edit_pages.php`, `includes/explorer/recycle_bin.php`).
- Not read again: the review rules hunted in `bug-hunt-customer-reviews.md`, prototype comments (`bug-hunt-areas-prototype-comments.md`).

## What did it fix?
- `convert_text_to_html()` put each address of a comment's text into a `preg_replace()` replacement. A `$0` or `\0` in one address wrote the placeholder into its link's href, the next link then landed inside that attribute, and the visitor's second address stood as bare attributes of the tag (`onmouseover=…`): a script in a published comment, or in a held one a moderator opens on the page.
- Text typed like the placeholder moved links too. The text is now cut at each address's offset and escaped piece by piece; the output of ordinary text is unchanged (`tests/convert_text_to_html_test.php`).
- Deleting a page (Edit Page, the Pages list, emptying the Recycle Bin) deleted its comments but not their panel notifications: Manager and above kept a bell entry "There is a new  exist." with an empty label whose link opened `edit_comment.php` without an id. `pg_comment_delete_page_notifications()` runs before each path's comment delete (`tests/comment_page_delete_notifications_test.php`).

## What is left for a decision?
- Deleting a product (product builder, the Products list, the API) leaves its reviews and questions in `comments` with no product behind them; they stay on the Comments list. Whether they go with the product, or stay for the history, is a behaviour choice: listed in the hunt's PR body, not changed.

## What was read clean?
- Held comments: the classic block and the widget read `published = '1'` plus the writer's own of this session (`pg_comments_visible_sql()`); the recent widget's `pending` audience only for staff; record counters (`pg_comment_refresh_record_info()`) and site search count published only.
- Escaping: name (`h()`, `pg_comment_list_name()`), message (`convert_text_to_html()` after the fix), attachment name and address (`h(encode_url_path())`), the store reply.
- Spam and rate limit: CAPTCHA when switched on; `add_comment.php` is one of `waf_sensitive_scripts()`.
- Attachment: refused before the row is written (`pg_upload_file_refusal()`, `pg_upload_name_blocked()`), review photos by type; HTML and SVG are sent by `get_file.php` as attachments with nosniff.
- Mails: the administrator mail is plain text and queued; watcher and submitter mails only for published comments, the writer left out; the scheduled publish claims each comment once.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-customer-reviews.md
