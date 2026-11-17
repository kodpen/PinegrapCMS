---
name: php-error-log-clean-census-3
description: Error log census part 3 (plan php-error-log-clean): the files with shapes still open, from init.php to the smallest; verdict words are explained in part 1
---

## Which files still hold open shapes? (part 3 of 3)
- `init.php` 16: key "REQUEST_METHOD" 6 (command-line runs) and headers already sent 4 — still. Session ini_set() while a session is active 2, session_start() permission denied 1 — check.
- `includes/api/console_view.php` 15: `params` missing and foreach() of null — check.
- `add_theme_file.php` 15: headers already sent 1 — still.
- `includes/fn/core.php` 14: one 120 s query from `translations_action.php` (12 Oct) — check; the other fatals here are gone (part 1).
- `get_page.php` 13: undefined `$output_grid_button` — set since.
- `get_view_order_screen_content.php` 13 and `get_registration_entrance.php` 8: properties without "page_id" and "device_type" — still.
- `includes/fn/content.php` 10: undefined `$page_type` — set since. include() of a missing file 6 — still.
- `includes/erp/money.php` 8, `includes/erp/invoice_manual.php` 8, `includes/erp/quotes.php` 6: "The float … is not representable as an int" on very large amounts — still.
- `includes/workspace/notes_import.php` 6: its own warning "creating the stage folder failed" — still, written on purpose.
- `includes/api/openapi.php` 5: `params` missing and foreach() of null — still (the router's family).
- `includes/dompdf/…/FontLib/BinaryStream.php` 4: float to int cast — still; the library stays unpatched (`core/dompdf-embedding.md`).
- `includes/designer_screen.php` 4: headers already sent — still.
- `add_comment.php` 4: undefined `$file_size` — set since.
- `includes/forms/verified_shipping_address_form.php` 4: array to string conversion — check.
- `view_submitted_forms.php` 4: array offset on null — still.
- `delete_users.php` 2: array offset on null — check.
- `add_page.php` 2: key "folder" — check.
- `includes/fn/seo.php` 1: array offset on null — check.
- `view_folder_and_files_f.php` 2: fatal, `PG_FUNCTIONS_DIR` undefined on a direct request — still, #10052.
- `delete_fields.php` 2: key "fields" — still.
- `mysqldump.php` 2: fopen() of a backup file name that cannot be opened — still.
- `chat.php` 2 and `view_returns.php` 2: array to string conversion — check. `view_return.php` 2: the same — still.
- `includes/phpmailer/src/PHPMailer.php` 1: openssl_sign() with a DKIM key that is not a private key — still; a setting, not code.
