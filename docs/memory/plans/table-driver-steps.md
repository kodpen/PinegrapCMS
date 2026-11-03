---
name: table-driver-steps
description: Steps of the table driver plan: the driver core, its parts, the server-paged, quick edit and log lists (TD1-TD8)
---

## Which steps, in one line each?
1. TD1 (L) done (#6226). Server core: `includes/fn/list_table.php` column model (key, heading, width, kind, sortable, editable), the first rows printed, the rows answer, keyset paging over `list_paging.php`, the selection resolver ({ids} or {condition, exceptions, as of}, with the list's own query and rights); tests.

2. TD2 (L) After TD1. Browser core in `assets/js/pg_list_table.js`: rows, selection and its bar, Ctrl+A by condition, the undo of a replaced selection, rows on scroll, sort without a reload, the fill-height layout with a sticky header, empty, loading and failed states, CSS; the Pages list (`view_pages.php`, server-paged since #6114) moves with its bulk actions.
   Cut in two: TD2a (#6246) the browser core (`pg_list_table.js`, selection model, CSS, bundle, Node test of the selection model), no screen on it yet; TD2b done (#6304): the Pages list moves with its bulk actions through the resolver.

3. TD3 (M) done (#6321). Columns menu (show, hide, order, reset), the details panel and its side sheet below 1024 px, both in localStorage; phone cards below 768 px; the thumbnail queue.

4. TD4 (M) After TD2. Users, contacts and submitted forms move with their bulk actions (export, merge, delete all) through the resolver.

5. TD5 (M) After TD2. Commissions, recurring commission profiles and the mail queue move.

6. TD6 (M) After TD2. Site Log (its type filter as a driver filter, today `view_log.php` calls `column(1).search()`), the audit log and the firewall log move.

7. TD7 (L) After TD3. Quick edit mode ("Hızlı düzenleme", F2, per-cell save, undo, apply to the selected rows) over `pg_list_edit_request()`; the products list moves.
   Cut in two: TD7a done (#6337) the quick edit mode in the driver (`pg_list_table_edit.js`, `ui/list-table-quick-edit.md`), no screen on it yet; TD7b next: the products list (`view_products.php`, `product_list.php`) moves with its bulk actions and its quick edit columns (source `edit`, cells with `pg_list_edit_cell_attributes()`, column keys matching the edit fields).

8. TD8 (M) After TD7. Orders, gift cards, key codes and shipping methods move; then the ERP lists with editable cells: account prices, stock minimums, price lists, stock levels.

## What runs side by side?
- TD3, TD4, TD5 and TD6 touch different screens and can run together after TD2; TD7 waits for TD3. The printed lists and the close-out are in `table-driver-steps-2.md`.
