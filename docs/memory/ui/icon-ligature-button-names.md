---
name: icon-ligature-button-names
description: Buttons whose content is a Material Icons ligature (north, south, delete, living) are named by that word; how the product attribute and product group screens name them (#2893)
---

## Why does a screen reader say "north" or "living"?
- A ligature button (`<a class="material-icons">north</a>`) has the ligature as its text, and text beats `title` in the accessible-name order, so it read "north", "south", "delete" in English on every panel language. Bootstrap's `_fixTitle()` skips an element with text, so the title popover did not help either.

## How is such a button written?
- `<button type="button" aria-label="…">` with the ligature inside `<span class="material-icons" aria-hidden="true">`; the label comes from `lang()` in PHP or the screen's label map (`h(label['Move Up'])`) in JS-built markup.
- Reorder buttons set the real `disabled` property (`.prop('disabled', true)`), not only a `.disabled` class, so the first row's Move Up is out of the tab order.

## Where is this done?
- `init_product_attribute_options()` (option rows: Move Up, Move Down, Remove; the option box is named `Option`) and `init_product_group_attributes()` (the attribute name and "Default Option" labels point at `default_option_id_<id>`) in `backend.src.js`; Modify Selected on `edit_product_group.php`.
- Product group products table: the row checkbox is "Select {var:1}" and the sort box "Sort Order: <name>" in `edit_product_group.php`, `product_group_form.php` and its Load More twin in `includes/panel/commerce.php`.
- The Catalog Name icon label (`public`) in `product_group_form.php` is `aria-hidden` without `for`, so it no longer joins the field's name.
- Find & Replace (`find_and_replace.php`, #2954): the Find, Find & Replace, Back and result-link ligatures are `aria-hidden`; the Scope header is a `<button>` with `aria-controls`; results tables sit in `table-responsive`; counts are `{var:1} record(s)` and `{var:1} matches found in {var:2} records`. Test: `tests/find_replace_results_test.php`.
- SMTP Settings, System Status, Performance Log (`tests/system_screens_names_test.php`): the Send Test, Generate DKIM Key, Test DKIM and Save ligatures are `aria-hidden`; the TXT record box in the post-generate DKIM notice is `#dkim_notice_record` with a `for` label; the e-Fatura tile dates with `get_date_format_code() . '/Y'` and writes shares as `{var:1}%`; `pmon_sort_header()` puts `aria-sort` on the active column.
- Strings that JS-built markup reads with `pgLang()` (image picker tile Remove, barcode dialogs' Close) are in the `$translate` map of `includes/fn/output.php`.

Test: `tests/product_group_accessible_names_test.php`.
