---
name: erp-document-templates-settings
description: Per-document template settings in erp_settings.php (config.erp_<doc>_template, $documents map, preview) and how placeholder references are built from data.
---

## How are ERP document templates stored and edited? (migration 4.60)
- `config.erp_<doc>_template`: NULL = the built-in file (`*_default_template()`).
- `erp_settings.php` has a `$documents` map (`label`, `column`, `default`, `current`, `preview`, `log`); the tab is `?doc=`. If the 4.60 columns are missing, that document does not appear in the tabs (`waf_table_has_column`).
- `*_html($id, $template = null)`: `null` = saved/built-in, a string = preview.
- Preview PDF scripts take `POST template` only with `validate_erp_access($user, 'settings')`.
- The Preview is the PDF: a posted template goes through `erp_template_preview_send()` (`includes/erp/settings_cards.php`), which renders with `erp_invoice_pdf()` (no remote files, DejaVu Sans, chroot, `erp_paper_size()`) and streams it inline with `Cache-Control: private, no-store`, before any kept copy is read or written. Only when the library is missing or dompdf throws does it show the HTML, opened by a one-line note. Before, Preview was the HTML, so a remote logo, a web font, flex/grid or `text-transform:uppercase` looked right there and wrong in the customer's PDF. `?html=1` still returns the HTML. Test: `tests/erp_template_preview_pdf_test.php`.

## How is the placeholder reference built?
- From the data itself: `erp_settings_placeholders_from_data()`. An ordered list (`array_keys === range`) is shown as a block with the first element's fields joined by " › "; bool → Doğru/Boş; a value is cut to 60 characters (like `logo_data_uri`). `seller.logo_data_uri` is large but harmless because it is cut.

Related: docs/memory/erp/erp-invoice-document-pdf.md
