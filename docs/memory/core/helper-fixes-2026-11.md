---
name: helper-fixes-2026-11
description: Order status stage of negated words, plain-text mail entities, responsive images with quotes inside values, short link names after leading spaces: what each helper does now
module: ecommerce, mail, image, short_link_redirects
---

## How does _pg_order_status_stage() read a negated status?
- The values the software writes (`incomplete`, `complete`, `exported`, `cancelled`, `canceled`) are matched exactly first; any other value falls to the substring checks, where `incomplete`, `unpaid` and `tamamlanmad` are checked before `complete`, `tamam` and `paid`.
- Symptom before (#7665, #7692): `strpos('incomplete', 'complete')` matched, so a saved basket got the `complete` stage and the green badge in the order view widget; `unpaid` and `tamamlanmadı` read as complete too. Tests: `tests/order_status_stage_test.php`.
- `mb_strtolower('İptal')` gives `i̇ptal` (dotted i), which the `iptal` check does not match; left as it was.

## What does convert_html_to_text() do with entities?
- The fixed list runs first (`&nbsp;` to a space, `&copy;` to `(c)`, `&#8217;` to `'`, ...); then every other entity is decoded with `html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')` after `strip_tags()`, so an escaped tag such as `&#60;b&#62;` stays as text in the plain-text part; an entity PHP does not know is dropped (`/&[a-z0-9#]+;/i`). `&lt;`/`&gt;` are in the fixed list, decoded before `strip_tags()`, and still removed with the tag they spell, as before.
- Symptom before (#7740): the drop pattern `/&[&;]+;/` never matched a real entity, so `&hellip;`, `&rsquo;`, `&euro;`, `&#8230;` stayed raw in the plain-text part of every HTML mail (`email()` sets `AltBody` from it). The `get_page.php` meta descriptions still call `html_entity_decode()` on the result; that is harmless. Tests: `tests/mail_html_to_text_test.php`.

## When does pg_img_responsive_html() leave a tag alone for its quotes?
- Only when `pg_img_tag_quotes_closed()` says a quoted value is still open at the end of the tag: outside a value `"` or `'` opens one, inside it only the same quote closes it. That is the case of a `>` inside a value ending the match early.
- Symptom before (#7719): it counted `"` and `'` separately, so one apostrophe in a double-quoted alt (`alt="Men's"`, `alt="İstanbul'da"`) skipped the tag and the image got no `srcset`/`sizes`. Tests: `tests/image_responsive_test.php`.

## Does a leading space keep the base path on a short link name?
- No: `pg_short_link_name_from_address()` decides "path from the root or full address" on the trimmed value, so `' /site/blog'` and `" \thttps://example.com/site/blog"` give `blog` under base path `/site/` (#7720). Tests: `tests/short_link_redirects_test.php`.

## Is the zone abbreviation of a short absolute time translated?
- No (#7742 did not reproduce). `strtr()` is case-sensitive and the day name keys are `We`, `Sa`, ...; every abbreviation in PHP's zone database is upper case or numeric (`WEST`, `SAST`, `+03`), the one mixed-case `ChST` matches no key. `tests/absolute_time_datetime_test.php` pins `Per 15 Eki 2026 20:11 WEST` and `... 21:11 SAST`.
