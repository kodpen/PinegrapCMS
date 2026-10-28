---
name: photo-gallery-widget
description: The Photo Gallery widget (classic "photo gallery" page type): the folder it shows and the ?folder_id= scope check, album and photo rows, tokens, flags, what is left out.
module: widgets_photo_gallery
---

## What is it?
- Kind `photo_gallery` (`includes/fn/widgets_photo_gallery.php`), step 5c (#2492) of [[system-widget-missing-types]]. The page's folder is the gallery: its sub-folders the visitor may view (`check_view_access()`) are albums, its images (`pg_gal_image_types()`, not design files, by name) are photos. One main loop: the albums, then the photos.

## Which folder does it show?
- `?folder_id=` opens an album on the same page, as on the classic page, only when the folder lies below the page's folder (`pg_gal_folder_shown()` walks the parents) and the visitor may view it; anything else shows the page's folder. The classic page sent a visitor without access to a sign-in screen; the widget shows the gallery's top instead. A background render ignores the address.
- `__back_url`: the album above (the page without `folder_id` when that is the gallery's top); flag `in_album`.

## Which tokens and flags?
- Row tokens (`pg_gal_rows()`): `__gallery_item_id`, `__gallery_item_name`, `__gallery_item_url` (album on this page / the image), `__gallery_item_image_url` (the photo, an album's first photo, empty for an empty album), `__gallery_item_description`, `__gallery_item_photo_count`, `__gallery_item_count_label` (the classic "3 Photos"), `__gallery_item_is_album`, `__gallery_item_is_photo` (for `show_if`).
- Static: `__album_name`, `__back_url`. Flags (`pg_gal_flags()`): `has_gallery_items`, `no_gallery_items`, `has_albums`, `has_photos`, `in_album`.

## What is left out?
- The page's `number_of_columns` and `thumbnail_max_size`: the starter's grid (`row-cols-*`) and square `ratio` boxes are the designer's to change. The classic script viewer (`software_init_photo_gallery`) and the image edit buttons: a photo links to the image. The custom layout. No PHP default tree, only the JS starter.
