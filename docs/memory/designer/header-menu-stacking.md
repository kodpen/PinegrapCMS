---
name: header-menu-stacking
description: The account menu (login region) and language list of a template's account bar come down over a sticky header: pg_sw_lift_header_menus(), the 1025 level, the covered first rows it fixed
module: widgets_account
---

## What was wrong?
- Market, News, Launch, Charity and Social place an account bar (notice, language switcher, login region, cart link) above a sticky header (`sticky-top`, or News' `.pn-nav` / `.pn-masthead`, both 1020). The login region's account menu is a Bootstrap 5 dropdown at 1000 in the page's root stacking context, so the header, later in the page, painted over its first rows: the member's name and e-mail sat under a strip (owner report 2026-11-07).
- Templates with a static header (Café, Gym, Hotel, Law, Store, School, Forum and most others) were never covered.

## How is it fixed?
- At render, not in the templates: `_render_system_widget_login_region()` runs `pg_sw_lift_header_menus($tree)` on the drawn copy, which adds `z-index:1025` (`pg_sw_header_menu_z_index()`) to the inline style of every node whose classes hold `dropdown-menu`. Stored trees are untouched, so designs on running sites are fixed without a migration or a template version.
- `pg_language_switcher_html()` prints the same level on its `<ul class="dropdown-menu">`.
- A `z-index` the design wrote in the node's own inline style is kept.

## Why 1025?
- Above `.sticky-top` (1020); below `.fixed-top` (1030), the offcanvas (1045) and the modal (1055), so a drawer or dialog still covers an open menu. A menu inside a positioned header lives in that header's stacking context, where the number changes nothing.

## Is it tested?
- `tests/site_header_menu_stacking_test.php`: the level's range, the tree walk, the login region call, the language list's markup.
- Seen in Chromium at 1280, 1100 and 390 px on the twelve templates above, installed with `pg_design_template_install()`; a unit test cannot see stacking, so a browser run checked that no point of the open menu belongs to the header.
- The editor's corner box over a site page (`ui/site-button-box.md`) also sits over the account bar's right end for staff; that is the decided corner, folded away with its arrow.
