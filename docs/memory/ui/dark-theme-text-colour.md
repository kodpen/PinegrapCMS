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

## What is left?
- `view_order_report.php` (period badge) and `view_users.php` (access menus) still carry `fg-black`; they were held by other PRs at the time.
