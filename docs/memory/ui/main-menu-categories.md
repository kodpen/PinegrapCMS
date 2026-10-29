---
name: main-menu-categories
description: Main menu rows in categories (pg_menu_categories(), pg_menu_layout()): which rows, no heading over nothing, the File Manager row with the files, retired slot 3, store row names, heading look
---

## Which categories are there?
- `pg_menu_categories()` (`includes/fn/output.php`, #2811), drawn in this order: untitled (Dashboard 0, Workspace 23, pinned My Notes 24); Content (Pages 2, File Manager 1, Calendars 4, Forms 5); Design (Visual Page Editor 21, Page Styles 17); Ecommerce (Orders 10, Products 11, Catalog Manager 12, Offers 14, Shipping 15); Accounting (ERP 22: its own switch and rights, it runs without the store); Marketing (Visitors 6, Contacts 7, My Campaigns 9, Ads 16); System (Users 8, Site Settings 20, last). Owner's choice of the headings layout over one row per category: no store screen costs an extra click.
- The table sets the order; the rows are still built in id order, each under its gate. The old reorder loop ("moved rows") is gone. A new row needs a place in the table, or it is drawn last without a heading. `tests/menu_category_test.php` reads the built slots from `output_menu()` and wants each listed once.

## When is a heading drawn?
- Only over rows built for this person: `output_menu()` collects the slots that got an `id` and `pg_menu_layout()` keeps a category only if one of its slots is there. No gate is written a second time and no panel's links are counted (row 23's panel is empty when drawn). The owner asked for it: a category the person cannot reach shows nothing.
- Also over a single row (owner): order reports only gives Ecommerce over Order Reports; a workspace-only member gets no heading at all.

## Where did the files go?
- The File Manager row (slot 1, `view_folders.php`) is the destination; its panel: File Manager, Create Folder, Upload Files or Folders, Create File, then the explorer's tree with its views (Files, Pictures, Pages, ...) and folders (`explorer-menu-tree.md`). The Files row (slot 3) is retired, like 13, 18 and 19; never reuse it. Every `view_folders.php` view, the folder screens and the old file screens mark row 1; the tree marks the open view.
- The explorer switches views without a page load; it writes the address only through `setAddress()` (`view_folders.php`), which calls `pgMenu.refreshCurrent()`.
- The `files` drawing of `pg_menu_icon()` went with it.

## Why are the store rows renamed?
- Under the Ecommerce heading "All" said nothing: Orders, Products, Offers, Shipping (existing keys). Each first entry keeps "All ..." and carries `data-pg-menu-label` with the row's name, so `markMenuCurrent()` writes no hint on the list screen. The product attribute screens mark Products (11); they named row 13, which is never built.

## How does a heading look?
- `pg_menu_heading()`: a `div.pg-menu-heading` (not a link, not a `.list-group-item`) inside `.pg-menu-main`, so the menu scripts, the curtain and `drawMenuRow()` pass it by. Labels showing (expanded, phone drawer): a small upper-case title in the Workspace list's section-title type. Rail (lg and up, not expanded): a 28px line between the icons, the name kept for screen readers. A drilled menu hides headings with the rows.

Related: docs/memory/ui/main-menu-panels.md, docs/memory/ui/main-menu-entry-gates.md
