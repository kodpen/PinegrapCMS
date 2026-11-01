---
name: design-template-hello
description: Hello template 2.6.0: threads and the reply e-mail card on the Comments widget, product reviews, the Favourites page and its shop-only links, Google Reviews beside the testimonials
---

## Why 2.6.0?
- Owner, 2026-11-01 (`workflow/decisions-made-product-16.md`): every template on the Comments widget, testimonials kept as samples with Google Reviews beside them, reviews on the Hello product page. The Favourites page brings Hello level with the store, boutique and bookshop ([[wishlist-widget]]).

## Which comments widgets?
- The shop templates' four, same options ([[design-template-shop-comments]]): `post_comments` (blog_post), `conversation_replies` (conversation), `email_comment` (`$mail_comment_tree` on email_reply, right before the System region, which stays), `product_reviews` (product, review-worded tree, shop only).
- `$comment_thread($w)` draws Hello's look: white `card border-0 shadow-sm` comments with a `bi-person-circle` name line, a `card card-body border-0 shadow-sm p-4` form, `btn-primary px-4`, `btn-outline-secondary` staff buttons, a `Log In` button in the sign-in box (Hello's word for signing in). Words: blog "Add a comment" / "Post comment"; conversation "Write a reply" / "Send reply". Labels `Comment`, `reply`, `Review` ([[design-template-comment-labels]]).
- White, not the store's grey card: the primary Reply link is not readable on `bg-body-tertiary` ([[design-template-text-contrast]]).
- `$comments_section($name, $key, $class)` puts each in the reading column (col-lg-8, centred); the reviews in a `py-5 border-top` "Reviews" section under `product_detail`.

## What does the product page carry?
- `comments`: label Review, stars, sign-in, auto-publish, notify `{{site_email}}`, no e-mail page, as the store. The page is a designed product page: the widget reads the product's thread ([[comments-widget-product-page]]).

## How is Favourites placed?
- Page `favourites` (public folder, `requires` ecommerce, noindex, no sitemap or search) with widget `wishlist` (starter, `login_page_id`, `shop_page_id`, 100 rows).
- A My Account card bound to `__my_account_wishlist_url` and a footer link `{{page:favourites}}`, written only when `$shop`: the footer and My Account are a shared part and a widget, which `requires` cannot trim. A site without the shop gets neither.
- The cards' hearts come from the catalog starters ([[wishlist-heart]]).

## Where are the Google reviews?
- Widget `google_reviews` (home, explicit tree) after the three sample quote cards in the Testimonials section; the samples stay for the business to replace. Everything is under `has_reviews`: nothing shows until the key and Place ID are saved and Google returns reviews; the installer preview leaves it out. The card keeps the author name and link, the review link, the Maps link and `__google_attribution` ([[google-reviews-widget]], [[design-template-threads]]).

## What happens to older sites?
- Nothing: rows are never reused, no migration. A 2.5.x site keeps the fixed block; Make designable gives the editor's widget.

## How was it checked?
- `tests/design_template_shop_comments_test.php` (Hello in its table); `tests/design_template_hello_test.php`: settings, reviews under the product, Google Reviews via `tests/helpers/template_threads.php` (it reads a section's `customName` too), the template loaded without the shop in a process of its own (no Favourites links); `tests/wishlist_widget_test.php`, `tests/comments_product_view_test.php`. No running instance.
