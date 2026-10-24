---
name: comment-replies
description: Which comment a reply may answer (pg_comment_parent_ok()): same thread, visible to the writer, else refused with the text kept; ?reply_to=, staff mails, API parent_id, deleted parents, page copies
---

## Which comment may a reply name?
- `add_comment.php` reads the optional posted `parent_id` ([[comments-table]]). `pg_comment_parent_ok()` (`includes/fn/comments.php`, pure) decides in order:
  - none (null, '', '0', 0): a comment that answers none;
  - malformed (array, bool, sign, fraction, letters, over 10 digits): refusal `value`, never read as 0;
  - no such comment: `missing`;
  - another thread (page_id, item_type, item_id; a non-value reads as '' / 0): `thread`;
  - not visible to the writer: `hidden`. Visible: published (`published = 1`, not a scheduled one), the writer's own of this session (`$_SESSION['software']['added_comments']`), anything for staff (`check_edit_access($folder_id)`) and the record's form editor.
- Thread before visibility. Viewer: `array('staff' => bool, 'form_editor' => bool, 'own_ids' => int[])`; not an array is nobody.

## When and how is a refusal answered?
- After the page gates, the thread refusals ([[comment-thread-verify]]) and the record's view check; before the review gate, the upload and the INSERT.
- Log, one `add_error()` sentence for every refusal (hidden reads as missing), `set_field('parent_id', array())` (a hidden field prints the session value first), redirect to `send_to#software_add_comment`. No `output_error()`: the typed text stays.

## What checks `?reply_to=`?
- `pg_comment_reply_to($value, $page_id, $item_type, $item_id, $viewer)`: the parent's row or null, asked before a prefill and for a `parent_id` kept after a refusal. No caller yet ([[designable-comments]]).
- A reply to one's own pending comment is stored: a quote or parent name shown to others comes from rows they may see.

## What does the staff e-mail say?
- add_comment.php's two administrator bodies and job.php's mail when a scheduled comment is published (`send_comment_email_to_administrators()`) put `pg_comment_parent_mail_line()` after the display name: `In reply to: <name> (#<id>)` on one line, '' for none. No new link (`mail_link_hostname_test` counts 3).

## What does the API give and take?
- `parent_id` (`integer?`); `GET /comments?parent_id=0`: comments that answer none, `?parent_id=<id>`: one's replies. The parent is left-joined (`parent_comment`): a reply whose comment was removed reads null, lists under 0. Not writable: no create endpoint; re-parenting could make a cycle.

## What happens to replies when their comment is deleted?
- Nothing: no delete (`edit_comment.php`, the API, page deletes) touches them; they read as top-level.

## What does copying a page do with replies?
- `duplicate_page_f.php` copies oldest first (a reply's id is above its comment's), maps old id => new id and writes `pg_comment_copy_parent_id()` (pure): the copy answered, 0 when not copied. Column named only when ready.

## What happens before 11.0.31 has run?
- `pg_comment_replies_ready()` (static `pg_schema_has()`) is false: no parent is read, a reply is refused as `missing`; INSERTs name the column only when ready; the API selects `0 AS parent_id`, `?parent_id=<id>` matches nothing.

## What does the classic block do with a reply?
- Ignores `parent_id`: it shows as a later comment.

## What is not handled yet?
- A forged reply on a product or event thread passes the review gate like any comment; those threads come to the widget later.

## How was it checked?
- `tests/comment_parent_test.php` (tables, no-db bridge, mail line, copy map, wiring, deletes), `tests/api_comments_test.php`. job.php mail, page copy: source and pure tests only.
- Sandbox, MariaDB 10.11 (2026-10-24), anonymous on a page thread: replies to a published comment and to one's own pending one stored; to another's pending one, another page's, an unknown id, `12abc` refused, text kept. Staff reply to a pending one stored; both mails carried the line. API field, filter, parent delete, dropped column as above.
