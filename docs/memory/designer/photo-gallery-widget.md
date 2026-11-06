---
name: photo-gallery-widget
description: Photo Gallery widget and classic page: folder setting, ?folder_id= and the Recycle Bin, photos and alt, tokens, flags, Where used, what is left out.
module: widgets_photo_gallery
---

## What is it?
- Kind `photo_gallery` (`includes/fn/widgets_photo_gallery.php`), step 5c (#2492) of [[system-widget-page-types]]. Its top folder is the gallery: sub-folders the visitor may view (`check_view_access()`) are albums, its images (`pg_gal_image_types()`, not design files, by name) are photos. One main loop: albums, then photos.

## Which folder is the gallery's top?
- Panel "Photo Gallery Settings" → Folder, `cfg.folder_id` (#4450, owner decision in `workflow/decisions-made-widgets.md`). Empty (0) keeps the page's own folder. Picker list: `shared_component` / `list_folders` (designers only, bin left out, `pg_gal_folder_choices()`), JS `_swFolderOptions()`.
- `pg_gal_top_folder()`: a chosen folder that is gone or in the Recycle Bin gives 0 and the gallery shows nothing (empty state), never the page's folder, which may be the site's root. The panel shows "#id (not found)" and a warning; restoring the folder brings it back.
- A top the visitor may not view shows them nothing. Each widget row has its own setting (two galleries, two folders); no placement key.
- Design templates: `folder_id` names a template folder only when a page of the design sits in it (`pg_dtc_tokenize_config()`); any other id is kept as is.

## How does ?folder_id= open an album?
- On the same page, as the classic page, only for a folder below the top (`pg_gal_folder_shown()` walks the parents) that the visitor may view; else the top. The walk stops at the Recycle Bin, and the bin is never an album (`_pg_gal_read()`), so a gallery on the root never opens binned folders. (The classic page sent them to sign in.) Each gallery opens `folder_id` only below its own top. A background render ignores the address.
- Classic album list: a membership album is listed by edit rights to that album, as opening it checks (#7104; it asked the folder shown, hiding it from its editors).
- `__back_url`: the album above (no `folder_id` at the top); flag `in_album`.

## Which files are photos, with what alt?
- `pg_gal_image_types()` for the widget and both layouts of `get_photo_gallery.php`: every `pg_public_image_file_types()` type plus classic `tif`/`tiff` (WebP/AVIF since #4361, #4335).
- Widget photos, album counts and first photos use `pg_gal_photo_sql()`: no design files, no attachments (`attachment = 1`: form/comment uploads, signatures; `get_file.php` serves them only to their record's readers, so they drew broken, #7211). The classic page still lists them.
- System layout alt: `pg_gal_photo_alt()`, the description, else the name's stem with dashes/underscores as spaces; the link has the description as `title` (#4342, #4361). Custom layout template: `alt=""`.

## Does Where used see a gallery's photos?
- Yes: `handle_file_usage.php` walks the file's folder and those above (`pg_gal_folders_showing()`; a file in the bin or below it gives none, #4754) and lists, under "Photo Gallery", the classic `page_type = 'photo gallery'` pages in them and the designed pages whose widget's top is one of them (`pg_gal_widget_pages_showing()`, nested placements via `pg_shared_component_usage()`, binned pages left out), each once (#4341, #4361, #4450).

## Which tokens and flags?
- Rows (`pg_gal_rows()`): `__gallery_item_id`, `__gallery_item_name`, `__gallery_item_url` (album on this page / the image), `__gallery_item_image_url` (empty for an empty album), `__gallery_item_description`, `__gallery_item_photo_count`, `__gallery_item_count_label` ("3 Photos"), `__gallery_item_is_album`, `__gallery_item_is_photo`. Static `__album_name`, `__back_url`. Flags (`pg_gal_flags()`): `has_gallery_items`, `no_gallery_items`, `has_albums`, `has_photos`, `in_album`.

## What is left out?
- The page's `number_of_columns` and `thumbnail_max_size` (the starter's grid is the designer's), the classic script viewer and image edit buttons, the custom layout. No PHP default tree, only the JS starter.
