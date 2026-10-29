---
name: edit-screen-wide-layout
description: Owner decision for panel edit screens: a wide screen is not spent on one column of stacked cards; form column, sticky status column, sticky save bar, modeled on product_builder.php.
---

## What did the owner decide?
- Full-width cards stacked in a single column look sprawling on a wide screen; edit screens must not keep repeating that. The design language (Bootstrap cards, the toolbar) stays; the panel runs Bootstrap 6. Reference implementation: `product_builder.php` (adding and editing a product). ERP screens apply it by default (`erp/erp-screen-status-column-pattern.md`).

## Which columns and classes?
| Part | Classes | Holds |
|---|---|---|
| Form column | `col-12 lg:col-8 2xl:col-9 order-1` | the cards the operator fills |
| Status column | `col-12 lg:col-4 2xl:col-3 order-2`, with a `position-sticky` box inside | what is READ: status badges, numbers, summary, preview |
| Save bar | `col-12 order-3 position-sticky` | the primary button |

## What are the placement rules?
- Below `lg` (1024px; `2xl` is 1536px) the columns stack in source order: write the status column AFTER the form column, so on a narrow screen the summary does not push the first field off screen.
- Stickiness goes on the box inside the status column, not on the column (the column is as tall as the row); the save bar is sticky on its own column.
- The status box sticks at `top:3.3rem`, below the 50px top bar; `top:1rem` slid its card title under the bar (`add_return.php`, `view_return.php`, `privacy_erase.php`, `privacy_export.php` fixed).
- Short cards are paired in one row or moved into the status column.
- A screen that departs from the pattern says why in a code comment.

## How does the save bar sit on a phone?
- Below 576px the shared rule in `backend.src.css` (`nav.buttons.navigation`) lays the actions two per row (`flex: 1 1 calc(50% - 0.25rem)`); an odd last action or a label too long for half takes the row. One per row made a 4–5 action bar a 190–240px sticky column over the form (`edit_file.php`, `view_orders.php` with rows ticked). Fix it there, never in a screen's markup. With the chat bubble the bar sits `--pg-chat-clearance` higher.

## Why does focus not land under the top bar or the save bar?
- `backend.src.css` sets `scroll-padding` on `html` (the panel scrolls the window): top `calc(var(--pg-navbar-h) + .5rem)`, more while a DataTables floating heading (`.dtfh-floatingparent-head`) exists; bottom while a sticky `nav.buttons.navigation` or a visible `.mpp-bulk` is on the page, more below 576px and with the chat bubble. Without it Shift+Tab, `#fragment` jumps and the skip link parked the target under the bar.
- So a jump target the window scrolls to takes at most `scroll-margin-top:1.5rem` (`#pg_pb_sec_`, `software_repair.php` `#rerun`/`#rollback`); 5rem would stack on the padding. `#pgset-`/`#pgsub-` keep 5rem. Each predicate is its own `:has()` (a nested one drops the rule). Pinned by `tests/panel_scroll_padding_test.php`.

Related: docs/memory/ui/admin-screen-toolbar.md
