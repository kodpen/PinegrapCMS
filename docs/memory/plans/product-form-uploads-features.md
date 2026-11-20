---
name: product-form-uploads-features
description: Plan product-form-uploads, features U1 to U16: the field and its rules, the shopper's upload, who opens the file, where the order shows it, its later life and the sweep of unordered carts
---

## What does the field editor offer?
- (U1) File Upload in the type list of a product form, a product group form and the variant-set template; Signature stays custom-only.
- (U2) The field's settings: its folder (`upload_folder_id`), the allowed types (images, PDF, or extensions typed in), the largest size in MB (never above `pg_upload_limits()['file_max']`), required or not. Kept in `form_fields.rules`.

## What does the shopper do?
- (U3) In every cart that draws product forms, a File Upload field draws a file input and the form posts multipart. One file per field and unit.
- (U4) A kept file shows its name, size and, for an image, a small preview, with Replace and Remove. A save without a new file keeps the stored one, in all three save paths.
- (U5) A refused file (type, size, upload error, a body the server discarded) is marked on its own field; the other answers are kept.
- (U6) A required file holds checkout like any required answer.
- (U7) Uploads go through a visitor rate limit; a line holds at most its quantity times its file fields.

## Who opens the file?
- (U8) `get_file.php` opens a product answer's file to the visitor whose cart holds the line, the signed-in buyer of the order, and staff who manage orders (`validate_ecommerce_access()`), whatever the folder's own access. Anyone else gets what a private file gives today.

## Where does the order show it?
- (U9) The panel order view, Print order, the packing slip, the order preview and My Account's order: the file's name as a link; a thumbnail for an image in the panel.
- (U10) The receipt e-mail (plain text, classic page, designed order view) names the file; no public address is mailed.
- (U11) The API: an order line's answers, a file as id, name, size, type and its `get_file.php` address. `php tools/check_api_schema.php`.
- (U12) The orders CSV export: the file's name in the answer's column.
- (U13) A product that submits a form at completion copies the file into the new submission as a file of its own (today the copy skips File Upload), so deleting one never takes the other.

## What happens to the file later?
- (U14) Removing a cart line, lowering its quantity, dropping saved lines and deleting an order delete the files with their answers, through one function every path calls.
- (U15) The privacy erase and export keep their work: the erase already collects these files by order.
- (U16) The general job deletes the uploaded files of a cart that never became an order, N days after the cart's last touch (N is the owner's answer to the architect's poll, message 10011); a cart that became an order is never swept.
