---
name: dark-theme-text-colour
description: Panel text on the page background is fg-body, never fg-black (black in both themes); a menu inside a scrolling table gets data-bs-strategy="fixed"
---

## Why were badges and totals unreadable in the dark theme?
- The Bootstrap 6 switch turned `text-dark` into `fg-black`, and `fg-black` is `--bs-black` in both themes: on the dark background it reads at about 1.2:1 (#2058).

## What do I use?
- Text on the page or card background: `fg-body` (follows the theme). `fg-black` only on a fixed light fill (`bg-warning`, `theme-warning`, `bg-info`).
- A subtle status badge: `badge badge-subtle theme-<colour>`, not `bg-subtle-<colour>` with a fixed text colour.
- A `data-bs-toggle="menu"` inside a DataTables scroll box (`.dt-scroll-body`, `overflow: auto`) is clipped; `data-bs-strategy="fixed"` on the toggle positions it against the viewport (`view_product_attributes.php`).

## Which places were fixed later?
- The sign-in identity badges (`pg_signin_identity_badges()`) and the find/replace row numbers of `duplicate_folder.php` dropped `fg-black`; the access menu names of `view_users.php` (`pg_user_list_names_cell()`) are `fg-body`; the firewall widget (`widget_21.php`) dropped the v5 `text-dark`, which has no rule in Bootstrap 6. `tests/dark_theme_leftovers_test.php` keeps them.

## What is left?
- `view_order_report.php` (period badge) still carries `fg-black`; it was held by another PR at the time.
- Inline light fills are in `dark-theme-fixed-fills.md`.
