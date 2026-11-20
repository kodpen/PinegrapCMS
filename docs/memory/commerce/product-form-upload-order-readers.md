---
name: product-form-upload-order-readers
description: How the order pages, prints and receipt e-mails show a product form answer's file: a link, a panel thumbnail for an image, the name alone in an e-mail
module: product_form_uploads
---

## Where does a product answer's file show after the order?
- Panel order view (`view_order.php`), Print order, the packing slip, the classic order preview and receipt pages, My Account's order (classic view order screen), the designer order preview and the designed order view's form lists. Built in #10724.
- All go through three renderers in `includes/fn/content.php` (`get_submitted_product_form_content_with_form_fields()`, `..._without_form_fields()`, `get_form_review_info()` for `product_form`) and `pg_sw_list_order_item_parts_read()` (`includes/fn/widgets_lists_order.php`).

## What is printed?
- The file's current name (`files.name` through `form_data.file_id`, `pg_pfu_order_file_name_sql()`), escaped, as a link to the site address get_file.php serves; get_file.php decides who opens it (`commerce/product-form-upload-field.md`).
- The panel order view (interface `backend`) adds an 80 px thumbnail for an image; Print order passes `print` and gets the link alone.
- A file deleted from Files since, or an answer with `file_id = 0`: the answer's own text, as before.

## What does a receipt e-mail carry?
- The file's name only, no address (U10): while `pg_order_complete_receipt()` renders a copy, `pg_order_receipt_audience()` is `customer` or `store`, and `pg_pfu_order_file_mode()` gives `name`. Covers the plain-text receipt, the classic receipt page and the designed order view mailed as HTML.

## Which functions? (`includes/fn/product_form_uploads.php`)
- `pg_pfu_order_file_mode($audience, $interface)` and `pg_pfu_order_file_html($file_name, $base_path, $mode)`, pure; `pg_pfu_order_answer_html($file_name, $interface)` reads the audience and `PATH`.
- Pinned by `tests/product_form_uploads_order_test.php`.

Related: commerce/product-form-upload-api-csv-completion.md, commerce/product-form-upload-field.md, commerce/product-form-uploads.md
