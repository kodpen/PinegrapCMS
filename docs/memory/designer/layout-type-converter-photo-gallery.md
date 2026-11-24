---
name: layout-type-converter-photo-gallery
description: A photo gallery page's layout file into the Photo Gallery widget's design elements (LE8a): the two loops as one list by row flags, what each piece becomes, the new flags, the starting-site texts
module: layout_convert
---

## What does the photo gallery's map do?
- `_pg_lc_photo_gallery_map()` in `includes/fn/layout_convert.php`, read through `pg_layout_piece_map('photo gallery')` ([[layout-type-converter]]). The widget's one list holds the albums of the folder shown, then its photos ([[photo-gallery-widget]]); the layout loops over `$albums` and `$photos` apart.
- A loop's map value `list:<row flag>` marks one kind of the list's records: its body is shown under that row flag (`gallery_item_is_album`, `gallery_item_is_photo`). `_pg_lc_merge_loops()` joins the kinds' loops side by side in one parent into one loop area, and drops the flag wrapper (`has_albums`, `has_photos`) around a loop alone.
- A kind's loop that is not beside the other (the stock template, albums and photos under their own headings) is dropped as `loop`: the widget has one list.

## What does each piece become?
- `h($album_name)` `__album_name`; `h($back_button_url)` `__back_url` (bound `href`).
- Album: `name` `__gallery_item_name`, `url` `__gallery_item_url`, `image_url` `__gallery_item_image_url`, `number_format(number_of_photos)` `__gallery_item_photo_count`, the template's words `__gallery_item_count_label`. `$album['number_of_photos'] > 1` (the plural "s"): row flag `gallery_item_many_photos`.
- Photo: `url` `__gallery_item_url` (its picture's address too), `name` `__gallery_item_name`, `description` `__gallery_item_description`.
- `$albums` `has_albums`, `$photos` `has_photos`, `!$photos` `no_photos` (else `has_photos`), `!$albums and $photos` `only_photos`, `!$albums and !$photos` `no_gallery_items` (else `has_gallery_items`), `$back_button_url` `in_album` (else `not_in_album`). `flag_list`: a flag may cover the list.

## What did the widget gain?
- `pg_gal_flags()`: `has_gallery_items`, `no_gallery_items`, `has_albums`, `has_photos`, `in_album`, and new `not_in_album`, `only_photos`, `no_photos`. `pg_gal_row_flags()`: the three row flags. Each row is drawn by `pg_gal_row_html()` from the list's nodes, its own flags deciding what shows (before, one template filled per row). The editor's flag list carries them.

## What did the starting sites give?
- 12 file pages, 11 texts (343, 994, 995, 996, 1049, 1050 on each site; 1049 the same on both): all convert with nothing dropped, so each converts in the move run. `tests/layout_convert_test.php` runs every text and draws converted rows through `pg_gal_row_html()`.
