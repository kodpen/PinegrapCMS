---
name: bootstrap-6-loader-metrics
description: Bootstrap 6 changed .placeholder min-height (1em to 1.25em) and .spinner-border size (2rem to 1em); the dashboard skeleton and four drawer spinners keep their v5 size; loaders that screen readers hear
---

## What did Bootstrap 6 change?
- `.placeholder` has `min-height: 1.25em` (v5.3.8: `1em`).
- `.spinner-border` is `1em` with a `.125em` ring (v5.3.8: `2rem` with `.25em`).
- The switch's shell rules keep v5 metrics for buttons, cards, menus and similar components, but not for these two (`bootstrap-6-codemod.md`).

## Why did the dashboard skeleton jump?
- The skeleton bars in `welcome.php` sit in 16px text with inline heights of 13px/11px; the v6 min-height turned each into 20px. A list row (`$pg_ph_row`) grew from 52px, the height of a loaded `.pg-row`, to 60px, so row n jumped up 8n px when `api.php` answered, about 40px on a five-row card. The short summary strips (`$pg_ph_blocks`) spilled further over their border.
- Fixed by one rule in its own `<style>` after the drag rules: `@layer custom { .card-body-placeholder .placeholder { min-height: 1em; } }`. The custom layer comes after components. The skeleton markup and its inline heights stay as measured.
- Rows whose tile sets the height (`$pg_ph_row_sm`), the 13px filter bar and the heading bars were not affected or got closer to the loaded size.

## Why were drawer spinners half size?
- A big loader in an empty drawer body drew at 16px with a 2px ring. Each has `style="font-size:2rem"`, which gives 32px with a 4px ring, v5's size, and sets no `--bs-*` variable: `add_order.js` (ERP account drawer, Recent Local Sales drawer), `erp_lists.js` (ERP list drawer), `seo.php` `pg_seo_render_detail_offcanvas()` (SEO Detail drawer; the markup is escaped PHP-in-JS).
- A drawer spinner is `role="status"` with a `.visually-hidden` child filled with `lang('Loading...')` (the ERP drawer's `data-erp-drawer-loading`, add_order's `t.loading`), never `aria-hidden`, which took it out of the accessibility tree. The ERP drawer is named by its title, so the title reads that text and the dialog is `aria-busy` until the record arrives.
- The Audit Trail changes fold shows `data-pg-audit-loading` with `aria-busy`, not `…`; `update_search_index.php` puts its sentence inside the `role="status"` span and writes the finish sentence into `#finish_status`, a status region printed in the header (`tests/loading_announced_test.php`).
- The dialog loader in `backend.src.js` and a central `.spinner-border` rule in `backend.src.css` are still open.

## How is it checked?
- `tests/bs6_js_leftovers_test.php` (`test_bs6_js_leftovers_loader_metrics`) pins the rule and the four spinners (#2659). The placeholder opacity (.18/.1 in v6) is the v6 design and was left.

Related: docs/memory/ui/bootstrap-6-codemod.md, docs/memory/ui/bootstrap-6-runtime-markup.md
