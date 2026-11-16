---
name: share-card-page
description: The share card's switch and colours in Site Settings, SEO, Social Sharing, which pages print a card as og:image, the sample and the record warning
---

## When does a page print it?
- Site Settings, SEO, Social Sharing, "Share card": settings text `share_card` = `1`, off on every site (owner, agent channel poll 70). "Card Background" and "Card Text Color" save `#rrggbb` or the default.
- `get_page_content.php` asks `pg_share_card_og_image()` just before `pg_og_default_image()`, so an item's own picture and the share image field stay first. `pg_share_card_target()`: never in e-mail, with a custom `og:image` meta tag, or an answer other than 200; a record (`$pg_blog_form_id`) or a product (legacy catalog detail, or `pg_sw_page_item()`'s `id`); else the page, only at its own address.
- A product group, a filtered list, a page number or a query keep the default: their title changes with the address, and one page entry per language would flip between them.
- The title is `$page_title` as og:title prints it, keyed by `FRONTEND_LANGUAGE`. With a card the page also prints `og:image:width` 1200, `:height` 630 and `og:image:type` `image/png`.

## What does the settings part show?
- The switch's reason line (`pg_share_card_reason_text()`): `server` as a warning on a host that cannot draw, else the note that titles with letters the font lacks keep the default.
- While cards are drawn, the home page's card in the saved colours, kept under the language `sample` so it never replaces the title the home page prints, and drawn before the part is printed (a picture asked for while it is drawn answers 503); and the Default Share Image warning about record forms is not shown (a card stands for their picture).

## How is it tested?
- `tests/share_card_test.php`: the switch, the targets, a custom og:image tag and an item's own picture winning, the served language's title, the colour values, the reason texts and the warning.
