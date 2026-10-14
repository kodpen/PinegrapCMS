---
name: erp-document-templates-settings
description: Per-document template settings in erp_settings.php (config.erp_<doc>_template, $documents map, preview) and how placeholder references are built from data.
---

## How are ERP document templates stored and edited? (migration 4.60)
- `config.erp_<doc>_template`: NULL = the built-in file (`*_default_template()`).
- `erp_settings.php` has a `$documents` map (`label`, `column`, `default`, `current`, `preview`, `log`); the tab is `?doc=`. If the 4.60 columns are missing, that document does not appear in the tabs (`waf_table_has_column`).
- `*_html($id, $template = null)`: `null` = saved/built-in, a string = preview.
- Preview PDF scripts take `POST template` only with `validate_erp_access($user, 'settings')`.

## How is the placeholder reference built?
- From the data itself: `erp_settings_placeholders_from_data()`. An ordered list (`array_keys === range`) is shown as a block with the first element's fields joined by " › "; bool → Doğru/Boş; a value is cut to 60 characters (like `logo_data_uri`). `seller.logo_data_uri` is large but harmless because it is cut.

Related: docs/memory/erp/erp-invoice-document-pdf.md
