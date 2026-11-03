---
name: comment-label-stored-and-mail-line
description: Empty or old default 'Comment' comment label shows the site's word; admin e-mail line starts with a capital; member's pending line in Turkish (#4791, #4872, #4877)
module: comments
---

## What does an empty comment label show?
- `get_comment_label()` (`includes/fn/core.php`) shows `lang('Comment')` ("Yorum") for an empty `page.comments_label`, and a label of the page's own through `pg_tr_label()`.
- `pg_comment_label_stored($label)` (`includes/fn/comments.php`) reads the exact English default `Comment` (trimmed) as empty, so pages saved with it show the site's word too. `comment` in lower case, `Review`, `Yorum` are kept.
- Edit Page leaves the field empty and shows `lang('Comment')` as its placeholder. Before (#4791), it put `Comment` in the field and the first save stored it: a Turkish site showed "Comment ekle" and the admin e-mail said "Bir comment eklendi". No migration: the stored rows are read through the helper.

## Why does the admin e-mail capitalise the label?
- `add_comment.php` starts the message line of both administrator e-mails (published, awaiting approval) with `pg_comment_label_line_start($comment_label, lang(array('info' => '')))`: first letter upper case, `mb_` functions, Turkish `i` as `İ`.
- Templates give conversation and forum pages the lower-case `lang('reply')` on purpose ([[design-template-comment-labels]]); the e-mail printed "yanıt: …" (#4877). The page label is unchanged.

## What does the member's own pending comment say in Turkish?
- `Your {var:1} is awaiting approval` is "Gönderdiğiniz {var:1} onay bekliyor" (`tr/y.json`): it starts with a capital and says it is the member's own. The label comes lower-cased (`widgets_comments.php`, the recent comments list, `get_page_content.php`). Before (#4872) it read "mektup onay bekliyor".

## How is it checked?
- `tests/comment_label_texts_test.php`: both helpers, source pins on `core.php`, `edit_page.php`, `add_comment.php`, the Turkish value. No browser run.
