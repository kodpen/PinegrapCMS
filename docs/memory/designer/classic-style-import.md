---
name: classic-style-import
description: A classic style's code read by the HTML importer (L1): placeholders to region nodes, meta_tags/stylesheet dropped, title and body on the root, site paths kept, what stays text
---

## What does pg_designer_import_html() do with a classic style?
- `pg_di_classic_placeholders()` (`includes/designer_import.php`) runs before libxml parses. It drops `<meta_tags></meta_tags>` and `<stylesheet></stylesheet>` (the page frame prints both) and lifts the region placeholders with `pg_di_lift_region_tags()` (`designer_region_import.php`, shared with R2/R3): `<pregion>`, `<system>`, `<cregion>`, `<dregion "attr">`, `<menu>` (its clearing div goes with it, with or without the space the code view writes), `<menu_sequence>`, `<login>`, `<ad>`, `<cart>`, `<tcloud>`, `<mobile_switch>`, `<pdf>`, `<!--pg-comments-block-->`. Each becomes the `region` node `_render_region_tag()` writes back as the same tag.
- Any document goes through it, not only a style: a pasted or zipped page holding the tags gets region nodes too.
- Names are decoded (`&amp;` to `&`): the emitter writes them with `h()`. `$ctx->region_lookup` (optional, as the `$lookup` of `pg_di_designer_region_tree()`) gets the decoded name and decides what a `<cregion>` becomes (common, designer, or a `shared_ref`); without it every one is a common region node.

## Where does a placeholder stay text?
- Inside a comment, script, style, textarea or title: those segments are never lifted. libxml ends a script at the first `</`, so a lifted marker there would cut the script. A commented-out region (style 754 of the starting site) is not on the page and gets no node.
- Inside a block kept as markup (iframe and the other opaque tags): `pg_di_restore_region_tags()` writes the marker back as the tag in every string of the tree.

## Title and body?
- Body `class`, `id`, `style` and other attributes land on the root as before (`cssClass`, `id`, `inlineStyle`, `_attrs`).
- A classic document (one that had `<meta_tags>`, `<stylesheet>` or a placeholder, `$ctx->classic`) with a non-empty `<title>` keeps it as root `props.title`. `generate_style_code_from_tree()` and its JS twin in `generateHTML()` print it in `<title>`; `get_page_content.php` shows it only for a page without a title of its own, as the classic style did. No panel field edits it yet. A plain imported page keeps its title on the page only.

## Site paths?
- `pg_di_is_absolute()` counts a reference starting with `{path}` or `{software_directory}` as absolute: kept as written, a head link or script becomes an external asset entry, no "not in the archive" warning. `get_page_content.php` fills both in on the assembled page.
- The framework filter still drops `{path}bootstrap.css` and `{path}jquery.min.js` unless `keep_framework` is set: the starting site's theme Bootstrap is not Bootstrap 5; the caller (L2) decides.

## Tests?
- `tests/designer_import_legacy_test.php`: every placeholder, names and lookup, text segments, site paths, and every classic style of both starting sites' SQL dumps converted and written back with the same placeholders in the same order.
