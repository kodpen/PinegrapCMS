---
name: list-label-sort
description: List columns that show a label for a stored code sort by that label (pg_list_label_order(), FIELD()); the link picker sorts in PHP with pg_list_sort_items()
module: list_paging
---

## Which columns sort by their shown label, and how?
- Page Type (`view_pages.php`, labels `get_page_type_names()`), Selection Type (products), Status and Frequency (commissions; Frequency also on the recurring commission profiles, as `IFNULL(period, '')` so One-Time has a place), Action (email campaign profiles, `$action_names`), Destination Type (short links, `$destination_type_names`), Action (firewall log, `pg_waf_log_list_actions()`).
- Their sort map entry is `pg_list_label_order($expr, $labels)`: `FIELD($expr, 'code', …)` with the codes ordered by label through `pg_geo_label_compare()` (the panel language's `Collator`, else `strcasecmp`). `pg_list_order_by()` adds asc/desc as for any column. Only codes of `[A-Za-z0-9 _-]` (empty too) are written into the query; a code outside the list sorts first.
- Before this fix the heading link sorted by the stored code, so in Turkish the column read shuffled ("Otomatik Seçim, Onay Kutusu, Bağış, Miktar"), and in English too where code and label differ ("Auto Campaign Sent" = `email_campaign_sent`, "Recorded" = `log`). DataTables' own ordering is off, so the link is the only sort.

## How does the link picker (editor_select_page_or_file.php) sort?
- In PHP, `pg_list_sort_items($items, $levels, $keys, $numbers, $labels)`: size and time as numbers, page type by its label, other text through `pg_geo_label_compare()`; ties by the first key (URL). It replaced `array_multisort()`, which compared bytes: lowercase names and names starting with Ç/Ö/Ş/Ü came after "Z". The offer editor's product group selects (`_pg_offer_product_groups()`, `edit_offer_f.php`) sort their "Parent › Child" labels the same way, no longer with `strcasecmp`.

## Which other lists sorted in PHP use the panel language's order?
- Edit Product Group and Featured & New Items: `pg_list_sort_by_order_and_name()` (`list_paging`), (int) sort order (an empty box counts as 0), then `pg_geo_label_compare()` on the name. Ties are the normal case, and DataTables' ordering is off, so the server order is what shows.
- Site Log with the error log merged in (`view_log.php`): the merge `usort` compares text with `pg_geo_label_compare()`; time and `log_id` ties as before.
- Workspace folder picker (`ws_library_folder_list()`): `ws_library_folder_name_compare()`, a Collator with numeric collation ("Klasör 2" before "Klasör 10"), else `strnatcasecmp()`, matching the File Manager.
- OneNote import notebooks and sections (`ws_note_onenote_notebooks()`): `pg_geo_label_compare()`.
- Before, these used `array_multisort()` on lowercased names, `strcasecmp()` or `strnatcasecmp()`: Ç, İ, Ö, Ş, Ü came after Z. Pinned by `tests/panel_list_locale_order_test.php`.

## What did not change?
- The lists keep their search label maps (`pg_list_search_where()` 'labels'); the CSV exports still write the stored codes. Countries are sorted by label the same way (`commerce/country-names-display.md`). Still sorted by code: the audit trail action and the orders status.
