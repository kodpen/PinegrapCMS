---
name: design-template-gallery
description: Art Gallery template (pinegrap-gallery): artists, exhibitions and works as collections, work enquiries and private viewing requests, editions in the shop, the Journal, pgl- look, tests.
---

## What does it ship?
- Public: `home` (`home_exhibitions`, `home_artists`, `home_posts`), `about`, `visit`, `advisory`, `faq`; `artists` (`?medium=`), `artist` (its works by the artist reference), `artist_new`; `works`, `work` (wall label, availability badge, links `enquire?artwork=`), `work_new`; `exhibitions` (`?status=`), `exhibition` (its works, opening night, links `viewing?exhibition=`), `exhibition_new`; `enquire` (`?artwork=`, `?kind=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; `viewing` (`?exhibition=`), `viewing_view`, `my_viewings`, `staff_viewings`; `privacy`, `email_reply` and the request e-mails; shop pages (`shop`, `product`, `cart`, `checkout`, `order`, `email_order`) with `requires => ecommerce`; the standard pages with the Journal (`blog`, `?topic=`), account and staff pages. The full list is `_test_gl_page_keys()`.
- Catalog: Signed Editions, Small Works and Exhibition Catalogues (shipped products); only shop pages and `address_book` need the shop. The works on the walls are never sold online.
- Shared: account_bar, notice, header, footer, staff_bar, viewing_band, email_header/footer. Order 133, icon `bi-easel2`, look `minimal`, palette `graphite`. Contact group `newsletter` (Gallery Newsletter). Built in #7926 to #8128 and the assembly.

## Why a main file plus a folder?
- As Museum and Pharmacy ([[design-template-museum]]): `pinegrap-gallery.php` includes `parts.php`, `cards.php`, `mail.php`, then `pages_public`, `artists`, `exhibitions`, `requests`, `shop`, `standard`, `account` in one scope (standard reads artists' list pieces, account the requests' list); returns kept as `$…_part` (`$gallery`, `$artists`, `$exhibitions`, `$works`, `$advisory`, `$topics`, `$testimonials` are parts.php's data).

## How does it work without new product behaviour?
- Artists, Exhibitions and Works are collections ([[collection-fields]]); a work names its artist and exhibition by reference ([[collection-references]]); Availability (Available, Reserved, Sold) is office-only and shown as a badge.
- An enquiry and a private viewing are requests, never a sale or a booked slot: pick lists by `_cf.url_param`, the viewing date with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists.
- Not built: an edition counter, work/product availability sync, online offers, a provenance register, a virtual viewing room.

## What is the pgl- look?
- `pgl-brand`, `pgl-display`, `pgl-eyebrow`, `pgl-hero` (white wall, hairline), `pgl-frame` (thin border round a work), `pgl-caption` (wall label), `pgl-quote`, `pgl-lift`, `pgl-price` (tabular figures); DM Serif Display headings, Inter body, a pair no other template uses (the assembly test checks it). Field ids `gl-`. Dates are words, no `opacity-*` on text.
- Turkish: "sanatçı", "sergi", "eser", "açılış gecesi", "özel gösterim", "edisyon", "sanat danışmanlığı", "Günce" for the Journal; the template is "Sanat Galerisi".

## Which tests cover it?
- `design_template_gallery_test.php` (data, `_test_gl_page_keys()`, `_test_gl_shop_keys()`) and the per-part tests (`…_account_test.php` loads the whole template); `…_assembly_test.php`: page order equal to the plan's pages, widgets placed once, placeholders, `requires`, contact search sections, `pgl-` rules, the font pair, four comment threads and the reply card, samples, the home's widgets.
- In the shared lists of the collection-form (`artist_new`, `blog_new`, `exhibition_new`, `staff_entry`, `work_new`), account-email, submitter-name, consent-privacy (`gl-enq-consent`, `gl-vw-consent`), faded-text and custom-form-rules (`gl-vw-date`) tests.
