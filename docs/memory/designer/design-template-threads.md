---
name: design-template-threads
description: Café, Clinic, Playground and Salon 1.1.0 comment threads on the Comments widget, the comment e-mail cards before the System region, Google Reviews beside the sample testimonials
---

## Which comments widgets does the Café ship?
- `post_comments` on blog_post: oldest, featured `first`, threaded, max_depth 2, 20 a page; Reply, Share, the follow box.
- `reservation_messages` (reservation_view), `quote_messages` (quote_view), `conversation_replies` (conversation): two-sided private threads under their `submitter_only` record view ([[comments-widget-contract]]). Oldest, featured `none`, flat, one page; no Reply, Share or follow box: the guest gets each answer by the page's e-mail, the café by `notify_email`.
- `email_comment` (`$mail_comment_tree`, the template's mail colours) right before the System region of email_reply, which stays ([[comments-widget-email]]).
- `$comments_section($name, $widget_key)` places them; `$comment_thread($w)` draws the look and words. The pages' `comments` arrays are unchanged. Labels: [[design-template-comment-labels]].
- Café 1.1.0 (#4509), as [[design-template-shop-comments]], explicit trees for the same reasons. Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`).

## And the Clinic, the Playground and the Salon?
- Clinic 1.1.0 (#4531): `post_comments`, `appointment_messages` (under `appointment_detail`, private) and `conversation_replies`; `email_comment` on email_reply; Google Reviews as below.
- Salon 1.1.0 (#4871, the Clinic's set): `post_comments`, `appointment_messages` (under `appointment_detail`, private; its empty line says "booking", the salon's word), `conversation_replies`; `email_comment` on email_reply in the salon's mail colours; Google Reviews after the three sample testimonials. The builders live in `pinegrap-salon/parts.php` and `mail.php`, the widgets in `booking.php` and `standard.php`.
- Playground 1.1.0 (#4566): `post_comments` keeps the page's open thread (`login => false`: a typed name and the CAPTCHA for a visitor) and `conversation_replies`, each inside the bordered card that held the block, under its heading. Two e-mail pages: `email_comment_card` on email_comment, `email_reply_card` on email_reply. No testimonials.

## What stands beside the testimonials?
- Café, Clinic and Salon keep their three sample cards (sample words to replace, owner, 2026-11-01). After them, in the same section, `google_reviews` with an explicit tree, all under `has_reviews`: nothing shows until the Places key and Place ID are saved and Google returns reviews; the installer preview (flags off) leaves it out. It keeps Google's attribution: author name and link, review link, Maps link, `__google_attribution` ([[google-reviews-widget]]).
- The sample cards cannot hide themselves: the widget's flags hold only inside its tree, and a `:has()` rule would hide them on the canvas too. The business deletes them once its reviews show.
- The editor's Google Reviews starter binds the author link's address, not the name; the templates' cards bind both.

## What happens to sites made from an older version?
- Nothing: rows are never reused, no migration. A 1.0.x site keeps the block; Make designable gives the editor's widget.

## How was it checked?
- `tests/design_template_{cafe,clinic,playground,salon}_test.php` through `tests/helpers/template_threads.php`: no fixed block, kinds, options, each thread under its view, flags, draws for a member, a visitor and staff (the Playground's open thread with a name and the CAPTCHA), each e-mail card's place, link and e-mail draw, the Google card for a place and not without. No running instance.
