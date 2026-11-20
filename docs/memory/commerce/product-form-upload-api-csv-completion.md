---
name: product-form-upload-api-csv-completion
description: A product form answer's file after the order: the API's answers on order lines, the orders CSV cell, and the copy into a form a product creates at completion
module: product_form_uploads
---

## What does the API give for a line's product form?
- `GET /orders/{id}`: each `items[]` row carries `answers` (`api_order_item_answers()` reads them in one query, `api_order_item_answers_present()` shapes them, pure). Built in #10639.
- One answer per unit and field: `unit`, `field_id` (null once deleted), `name`, `label`, `value` (a check box group joined by ", "), `file` (null unless a File Upload).
- `file` (object `OrderItemFile`): `id`, `name`, `size`, `type` (extension) and `url`, the site address get_file.php serves (`URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path(name)`). It opens for order staff and the signed-in buyer, not for the API key (`commerce/product-form-upload-field.md`).
- A file deleted from Files since: `value` keeps the name the answer had, `file` is null. The list endpoint carries no items, so no answers.

## What does the orders CSV show?
- Both exports of `view_orders.php` (one file and multiple files) print the file's current name (`files.name` through `form_data.file_id`) in the answer's column: `pg_pfu_answer_text()`. Without a file row the answer's own text stays.
- The multiple-files export now names its columns with `pg_form_data_name_sql()` too, so a blank stored name falls back to the field's name.

## What happens at completion when the product creates a form?
- A product's Submit Form "Create" list (Edit Product) now offers the form's File Upload fields (`get_custom_form_fields.php`); the "Update" list and the update-where list still leave them out (`backend.src.js`).
- Its value names the product form field as one variable alone: `^^photo^^` (`pg_pfu_submit_form_source_name()`, matched without case by `pg_pfu_submit_form_file_id()`). Any other value gives no file.
- `pg_order_complete_line_form_create()` calls `pg_pfu_copy_to_submission()` with the unit whose product form fills the form (`pg_order_product_form_number()`: the unit's own, else the first).
- `pg_pfu_copy_file()` copies the bytes (`pg_file_reader_path()`, so a file moved to storage is fetched) under a new unique name into the form field's `upload_folder_id`, a files row with `attachment = 1`. The submission's answer holds the copy: deleting the order's file or the form's never takes the other.
- No file, a failed copy or a failed answer write: the form is created without that answer, and a placed copy is taken back.
- Updating an existing form never sets a File Upload field (the update path still skips the type).

## What about the privacy export and erase?
- Unchanged: the export already lists each product answer's `data` (the file's name) and `file_id`; the erase collects the files by order (`pg_privacy_erase_scope()`).

Related: commerce/product-form-upload-field.md, commerce/product-form-upload-order-readers.md, commerce/product-form-uploads.md
