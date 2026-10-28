---
name: product-review-photos
description: Photo reviews (11.0.57; #2426, #2818): config.review_photos, which switch lets a thread take a file, the image check, forms, thumbnails in the classic list, moderation and the comments widget
module: reviews
---

## What was decided, and why?
- #2426 (1a): a product review may carry a photo by a site setting, off by default, images only, shown in the review list and in moderation. #2818 (a): one store-wide switch, `config.review_photos` (TINYINT(1) UNSIGNED, default 0, step 11.0.57); the page's attachment switch (`comments_allow_file_attachments`) is not asked on a review thread and keeps ruling every other thread.
- Running sites: a product page whose page switch took files stops taking them on reviews until Photo Reviews is on. Files already attached stay.

## Where is it decided?
- `includes/fn/reviews.php`: `pg_review_photos_on()` (`REVIEW_PHOTOS`, `init.php`, false before the column), pure `pg_comment_attachments_allowed($page_switch, $review, $photos_on)`, `pg_comment_file_is_image()` (jpg, jpeg, png, gif, webp), `pg_review_photo_ok($name, $tmp)` (name ending and `getimagesize()` type: a renamed file is refused), `pg_comment_image_thumbnail_html()`.
- A thread is a review by `pg_review_gate_applies()` (a product, a select group, review columns there).

## Who asks it?
- `add_comment.php`: right after the thread check it replaces `$comments_allow_file_attachments`; before the error redirect a review photo that fails `pg_review_photo_ok()` gets a field error ("A review photo must be a JPG, PNG, GIF or WebP image."). One file per comment, as before (`comments.file_id`).
- Comments widget: `$settings['attachments']` is replaced the same way before the facts, so `files_on` and the form's multipart follow it.
- Classic block (`get_page_content.php`): `$comments_file_field_on`; the field reads "Add a Photo" on a review thread. liveform prints no `accept`, so the browser offers any file and the server refuses.
- Site Settings › Commerce: "Photo Reviews" switch, shown and saved only once the column exists (`$review_photos_ready`, `pg_schema_has()`).

## Where does a photo show?
- Classic list, review threads only (`$comments_read['review']`): a thumbnail above the file link.
- Comment Moderation: a 50 px thumbnail beside the message in the list (`view_comments.php`), a larger one above the link on the edit screen (`edit_comment.php`), for any image attachment.
- Comments widget (2c3b): row flag `comment_has_image` (`pg_comment_file_is_image()` on the file name, any thread; off in a recent list and in an e-mail without the site's address). The default layout (PHP tree, JS starter, so the server fallback too) draws a "Photo" node above the Attachment line: an `img-thumbnail` image, `src` bound to `__comment_file_url`, max 160 px by the Sizing props. Canvas: the card shows it, the rated sample (photo.jpg) has the flag on.

## How was the step checked?
- Sandbox MariaDB (`tools/setup_sandbox.sh`), CLI upgrade `php install/index.php automated_upgrade` from `2026.4.10` twice (column dropped first, then kept with value 1): added, then left as it was.

## Where is it tested?
- `tests/review_photos_test.php`: file names, the switch table, the content check on a real PNG and a renamed text file, the wiring.
- Widget: `comments_widget_test.php` (row flag, default tree), `comments_widget_email_test.php`, `comments_recent_test.php`, `comments_widget_designer_test.php` (sample).
