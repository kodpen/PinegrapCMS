---
name: bootstrap-6-floating-labels
description: Bootstrap 6 floats a .form-floating label only when it comes before its field; the Modify Selected popups fixed (#2579), the test and the check_bs6.php check that keep it
---

## Where does the label go in a `.form-floating` wrapper?
- Before its field. v6 floats it with `.form-floating > label:has(~ .form-control…)` (dist `bootstrap.css` ~L4023), a selector that only sees later siblings. v5 had the label after the field (`.form-control ~ label`); in v6 such a label stays full size and centred over the chosen option.
- Site output (Bootstrap 5.3.8) keeps the v5 order; this rule is for panel markup only.

## Which screens were fixed?
- The Modify Selected popups (#2579): `edit_pages.php` (folder, page style, mobile style, site search, sitemap), `edit_files.php` (design, folder), `includes/templates/edit_products.php` (status, price change, tax rate, inventory, quantity process). They open from view_pages, view_files, view_design_files, view_products and edit_product_group.
- Same PR: the Site Search, sitemap.xml and Design labels pointed at `for="folder"`; each names its own select now. A stray second `</select>` after the mobile style select was dropped.

## What keeps it from coming back?
- `bs6_floating_labels_after_control()` in `tools/bs6_rules.php`: from a `form-floating` class to the next `</div>`, a `<label>` after a `<select>`/`<input>`/`<textarea>` or `output_field(` call.
- `tests/bs6_floating_label_order_test.php` runs it over every file the codemod converts, code marked `@pg-site-output` left out.
- `php tools/check_bs6.php --strict` reports it as "form-floating label after its field"; no false positive on the tree when added.

## What was not checked in a browser?
- No sandbox run; the order was checked against the v6 dist selectors only.

Related: docs/memory/ui/bootstrap-6.md, docs/memory/ui/bootstrap-6-codemod.md
