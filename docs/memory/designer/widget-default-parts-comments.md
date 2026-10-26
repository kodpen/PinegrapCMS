---
name: widget-default-parts-comments
description: The comment stars default part (W16, #1908): what replaced __comment_stars_html, its conditions and tokens, the flex rule, the e-mail characters, starters, canvas
---

## What does the part draw?
- `pg_sw_part_comment_stars()` (`includes/fn/widgets_parts.php`): `span.pg-comment-stars` with `role="img"` and `aria-label="{{__comment_rating_label}}"` ("Rated N out of 5", `pg_comments_rating_label()`), shown by `__comment_rating not_empty`; inside, for each place 1-5, an SVG drawing node (`contentType svg`, the `_pg_comments_star_svg()` markup) filled when `__comment_rating gte <place>` and one empty when `lt <place>`.
- Kinds `comments` (both modes: the server reads `regionType`; the editor lists `comments` and `comments_recent`). A node bound to `__comment_stars_html`, or a text that is the token alone, becomes the host; the starters and the forum template build the part directly.
- `pg_comments_stars_html()` and `pg_comments_stars_text_html()` are gone; the renderer no longer fills `__comment_stars_html`, and it left `pg_sw_raw_token_keys()` and `SW_ATTR_MARKUP`.

## Why a flex rule?
- The renderer writes each node on its own line, so the drawings have white space between them, which a browser draws as a gap. `.pg-comment-stars{display:inline-flex}` in `pg_carousel_layout.css` (v5) keeps them touching; the comments widget and the recent list load it when their output holds the class (`pg_sw_parts_css()`).

## What does an e-mail get?
- `pg_sw_default_parts_apply(..., $email = true)` runs `pg_sw_parts_mail_stars()`: inside each `.pg-comment-stars`, the star drawings (an SVG node whose markup has `bi-star` or `bi-star-fill`) become one `^^__comment_stars_text^^` (★ for each point, ☆ to five, `pg_comments_stars_text()`); other children stay. Visible text equals the old `&#9733;` span.

## New tokens?
- `__comment_rating_label` and `__comment_stars_text` are row values (`pg_comments_widget_row_values()`), offered as "Rating (words)" and "Stars (characters)". The label is one sentence because "words beside a value" is still open.

## What does the canvas show?
- All ten drawings (filled and empty) until W19's state preview; the aria-label shows the token.

## Tests?
- `tests/widget_default_parts_comments_test.php`: old span vs new by visible text, class order, filled count, aria-label, ratings 1-5; no rating draws nothing; literal token; e-mail characters; starters and forum template; editor twin under Node.
