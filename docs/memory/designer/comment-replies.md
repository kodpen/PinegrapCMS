---
name: comment-replies
description: Which comment a reply may answer (pg_comment_parent_ok()): same thread, visible to the writer, else refused with the text kept; ?reply_to=, staff mail, API parent_id, deleted parents
---

## Which comment may a reply name?
- `add_comment.php` reads the optional posted `parent_id` (column: [[comments-table]]). `pg_comment_parent_ok()` (`includes/fn/comments.php`, pure) decides, in this order:
  - none (null, '', '0', 0): a comment that answers none;
  - malformed (array, bool, sign, fraction, letters, over 10 digits): refusal `value`, never read as 0;
  - no such comment: `missing`;
  - another thread (page_id, item_type, item_id; an item type that is not a value reads as '', an item id as 0): `thread`;
  - not visible to the writer: `hidden`. Visible: published (`published = 1`, not a scheduled one), the writer's own of this session (`$_SESSION['software']['added_comments']`), anything for staff (`check_edit_access($folder_id)`) and the record's form editor.
- Thread before visibility. Viewer: `array('staff' => bool, 'form_editor' => bool, 'own_ids' => int[])`; not an array is nobody.

## When and how is a refusal answered?
- After the page gates, the thread refusals ([[comment-thread-verify]]) and the record's view check; before the review gate, the upload and the INSERT.
- Log, `add_error()` with one sentence for every refusal (hidden reads as missing), `set_field('parent_id', array())` (a hidden field prints the session value first), redirect to `send_to#software_add_comment`. No `output_error()`: the typed text stays.

## What checks `?reply_to=`?
- `pg_comment_reply_to($value, $page_id, $item_type, $item_id, $viewer)`: the parent's row or null. A form asks it before it prefills, and for a `parent_id` kept after a refusal. No caller yet: the widget form comes later ([[designable-comments]]).
- A reply to one's own pending comment is stored, so a quote or parent name shown to others must come from rows they may see.

## What does the staff e-mail say?
- Both administrator bodies get `pg_comment_parent_mail_line()` after the display name: `In reply to: <name> (#<id>)`, line breaks in the name turned into spaces. No new link (`mail_link_hostname_test` counts 3).

## What does the API give and take?
- `parent_id` (`integer?`); `GET /comments?parent_id=0`: comments that answer none, `?parent_id=<id>`: the replies to one. The parent is left-joined (`parent_comment`), so a reply whose comment was removed reads null and lists under 0. Not writable: no create endpoint, and re-parenting could make a cycle that never draws.

## What happens to replies when their comment is deleted?
- Nothing: no delete (`edit_comment.php`, the API, page deletes) touches them. They read as comments of their own.

## What happens before 11.0.31 has run?
- `pg_comment_replies_ready()` (static `pg_schema_has()`) is false: no parent is read, so a reply is refused as `missing`; the INSERT names the column only when ready; the API selects `0 AS parent_id` and `?parent_id=<id>` matches nothing.

## What does the classic block do with a reply?
- Ignores `parent_id`: it shows as a later comment.

## What is not handled yet?
- `duplicate_page_f.php` copies no `parent_id`: a copied reply reads as top-level.
- A forged reply on a product or event thread passes the review gate like any comment; those threads come to the widget later.

## How was it checked?
- `tests/comment_parent_test.php` (tables, no-db bridge, mail line, wiring, deletes), `tests/api_comments_test.php`.
- Sandbox, MariaDB 10.11 (2026-10-24), anonymous on a page thread: replies to a published comment and to one's own pending one stored with `parent_id`; to another's pending one, another page's, an unknown id, `12abc` refused, text kept, nothing stored. Staff reply to a pending one stored; both mails carried the line. API field, filter, parent delete, and the dropped column behaved as above.
