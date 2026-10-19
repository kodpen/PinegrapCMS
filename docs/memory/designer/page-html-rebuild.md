---
name: page-html-rebuild
description: Rebuild Page HTML (pg_designer_rebuild_page_code()): every designer page drawn again from its stored tree; when it runs, what stays, session cursor; per-body once-assets
---

## Why is there a rebuild?
- `page.page_tree_code` is the HTML drawn at save (`pg_designer_save_page()`), and the site serves it as stored. A renderer change (responsive images, the formats offered) reached a page only when somebody saved it again.
- `pg_designer_rebuild_page_code($after_id, $seconds)` (`includes/fn/designer.php`) draws every page with a `page_tree_json` again, as a save without changes would: body classes from the design, page name; tree, `page_timestamp` and `page_user` untouched; a body that comes out the same is not written. Translated bodies follow on their next request (their fingerprint is the source body's).

## Where is it started?
- Site Settings › Features › Image Optimization › "Save and rebuild page HTML" (`submit_rebuild_pages`, `includes/settings/features.save.php`), after the card's settings are written.
- Unasked when the image formats offered change in the same save: the `<source>`s of the Image elements depend on them.
- 15 s per press. A site that does not fit goes on from `$_SESSION['software']['page_rebuild']` (last page id, counts; ignored after an hour) on the next press. The notice says how many are left; the activity log gets one line when it ends.

## What is set aside while it draws?
- The session's pending form messages: the `messages` content node shows and uses them up at draw time, and they belong to the settings screen.

## Why does each page body have its own "once" assets?
- `_pg_lightbox_assets_once()`, `_pg_fancybox_assets_once()` and `_pg_widget_layout_css_once()` used a static flag for the whole request. A request that draws several bodies (the rebuild, or a designer save carrying every page that shows a changed shared component) stored every body after the first without Fancybox's script and styles: its carousel lightbox did not open.
- Now the flags live in `&_pg_once_state()`; `generate_style_code_from_tree()` starts each body empty and afterwards adds what the body wrote to what was written before, so a widget drawn later in the same request still does not repeat them (`tests/designer_page_body_test.php`).
- Carousel ids are `mt_rand()`: a page with a carousel always counts as "changed".

Related: docs/memory/designer/responsive-images.md, docs/memory/designer/multi-page-design-model.md
