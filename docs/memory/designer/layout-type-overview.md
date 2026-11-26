---
name: layout-type-overview
description: Start file for a page type's layout file turned into its widget's design elements (LE6-LE10d): what the converter is, the rules kept, which file holds each type's map
---

## What is it?
- A classic page with its own layout file (`data/layouts/<page id>.php`) of a mapped type becomes its page type's widget built from that file: the theme's markup stays, each PHP piece becomes a token, a flag, a section or the widget's list, a piece with no element is dropped and reported. Plan `plans/legacy-editable.md`.
- The move run converts a page only when nothing drops; otherwise the Region panel lists the dropped pieces and "Convert to design elements" converts after the designer's confirm.

## Which rules bind it?
- A layout becomes real design elements, never a PHP area; a piece with no element is dropped and reported (owner 2026-10-27, [[layout-form-converter]]). No schema. The layout file, the page type and its type row stay.

## Which file holds what?
- The converter, the piece map's keys and the run: [[layout-type-converter]]. The Region panel's dropped list and its Convert: [[layout-type-converter-editor]].
- One file a type family: [[layout-type-converter-order-form]] (LE7), [[layout-type-converter-photo-gallery]] (LE8a), [[layout-type-converter-catalog]] (LE8b), [[layout-type-converter-catalog-detail]] (LE8c), [[layout-type-converter-cart]] (LE9a), [[layout-type-converter-express-order]] and its render (LE9b), [[layout-type-converter-checkout-steps]] (LE9c), [[layout-type-converter-order-preview]] (LE9d), [[layout-type-converter-view-order]] (LE9e).
- LE10: [[layout-type-converter-account-forms]] (LE10a: login, password pages, profile, e-mail preferences), [[layout-type-converter-entrance]] (LE10b: registration, membership entrance), [[layout-type-converter-account-address]] (LE10c: my account, update address book), [[layout-type-converter-search-results]] (LE10d: search results).
- A family file also holds the converter rules its step added: LE9b `section:`/`state:` echo, loop `source:`, `replace`; LE9c a lone branch that only opens or closes wrappers, a table's heading rows beside the list, the section option `inner`; LE9d a section loop drawn once, a flag `in_tag => keep`, loop `widget`, a `<dl>` holding the list; LE10b echo `drop:`/`cut:`, an `if` drop's `code`, section `hold`/`button`; LE10c map `when` (value conditions), `page_links`, echo `unlink:`.
- LE10d: `list:<row flag>` loops under `lists`, each a place of the one list under its row flag.

## How is a type added?
- A map function `_pg_lc_<type>_map()` in `includes/fn/layout_convert.php`, the type in `pg_layout_convert_types()`, its type table in `_pg_widget_type_facts()` and its settings in `pg_layout_convert_state()` (`includes/fn/layouts.php`); flags and tokens the layout needs added to the widget and to its palette lists in `style_designer.src.js`; a test running every starting-site text.

Related: docs/memory/designer/legacy-overview.md
