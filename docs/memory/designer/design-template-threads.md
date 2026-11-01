---
name: design-template-threads
description: Café and Clinic 1.1.0 (then Playground) comment threads on the Comments widget, the reply e-mail card before the System region, Google Reviews beside the sample testimonials
---

## Which comments widgets does the Café ship?
- `post_comments` on blog_post: oldest, featured `first`, threaded, max_depth 2, 20 a page; Reply, Share, the follow box.
- `reservation_messages` (reservation_view), `quote_messages` (quote_view), `conversation_replies` (conversation): two-sided private threads under their record view (`submitter_only`, which hides the thread and refuses a post for anyone else, [[comments-widget-contract]]). Oldest, featured `none`, flat, all on one page; no Reply, Share or follow box: the guest gets each answer by the page's e-mail, the café by `notify_email`.
- `email_comment` (`$mail_comment_tree`, the Café's colours) on email_reply, right before the System region, which stays ([[comments-widget-email]]).
- `$comments_section($name, $widget_key)` places them; `$comment_thread($w)` draws the Café's look (`shadow-sm rounded-4` cards, `btn-primary`, pill staff buttons) and words ("Write to us", "Send message" on a booking or request). The pages' `comments` arrays (label, login, auto_publish, e-mail page, notify address) are unchanged. Labels: [[design-template-comment-labels]].
- Café 1.1.0 (#4509), as [[design-template-shop-comments]] did, explicit trees for the same reasons. Decision: owner, 2026-11-01 (`workflow/decisions-made-product-16.md`).

## What does the Clinic ship?
- The same, its look and colours: `post_comments` (blog_post), `appointment_messages` (appointment_view, under `appointment_detail`, `submitter_only`) and `conversation_replies`, private like the Café's; `email_comment` before the System region of email_reply; `google_reviews` after the home page's three sample testimonials (`rounded-4 bg-body` cards on the grey section). Thread cards `bg-body-tertiary rounded-4`, plain outline staff buttons. Clinic 1.1.0 (#4531).

## What stands beside the testimonials?
- The Café's "What guests say" keeps its three sample cards and its 4.8 summary, sample words for the café to replace (owner, 2026-11-01). After them, in the same section, the `google_reviews` widget with an explicit tree: everything under `has_reviews`, so nothing shows until the Places key and Place ID are saved and Google returns reviews; the installer preview (flags off) leaves it out. The card keeps what Google asks for: the author's name and link, the review link, the Maps link and `__google_attribution` ([[google-reviews-widget]]).
- The sample cards cannot hide themselves: the widget's flags hold only inside its own tree, and a page rule such as `:has()` would hide them on the canvas too. The café deletes them once its Google reviews show.
- The editor's Google Reviews starter binds only the author link's address, not the name; the template's card binds both.

## What happens to sites made from an older version?
- Nothing: rows are never reused, no migration. A 1.0.x site keeps the block; Make designable there gives the editor's widget.

## How was it checked?
- `tests/design_template_cafe_test.php`, `tests/design_template_clinic_test.php`, both through `tests/helpers/template_threads.php`: no fixed block, the widget kinds and options, each thread under its view, its flags, draws for a member, a visitor and staff, the e-mail card's place, link and e-mail draw, the Google card drawn for a place and not without one or in the preview. No running instance.
