---
name: php-error-log-clean-census-3
description: Error log census part 3 (plan php-error-log-clean): files that held open shapes, init.php to the smallest, each closed by its PR or left with its reason
---

## Which files held open shapes? (part 3 of 3)
- `init.php` 16: key "REQUEST_METHOD" 6 and headers already sent 4: gone, #9490 (`tools/campaign_profile_flow_report.php` ran with `HTTP_HOST` set; the header warning followed the printed one). Session ini_set() while a session is active 2: done, EL5 #10439 (`download_assistant.php` started its session before `init.php`). session_start() permission denied 1: the server's session folder, not code.
- `includes/api/console_view.php` 15: gone, #4129.
- `add_theme_file.php` 15: headers already sent 1, after a warning `generate_system_theme_css.php` printed: gone, #5899.
- `includes/fn/core.php` 14: one 120 s query from `translations_action.php` (12 Oct): the request ended inside `db()`, the log names no action and it never came back; left. The other fatals here are gone (part 1).
- `get_page.php` 13: undefined `$output_grid_button`: gone, #9106.
- `get_view_order_screen_content.php` 13 and `get_registration_entrance.php` 8: done, EL5 #10304.
- `includes/fn/content.php` 10: `$page_type` and the missing include, one request: done, EL5 #10304 (View Order default screen).
- `includes/erp/money.php` 8, `includes/erp/invoice_manual.php` 8, `includes/erp/quotes.php` 6: "The float … is not representable as an int" on very large amounts: done, EL5 #10329 (the exchange rate and the home-currency total bounded, `erp/erp-money-overflow.md`).
- `includes/workspace/notes_import.php` 6: its own warning "creating the stage folder failed", written on purpose; left.
- `includes/api/openapi.php` 5: gone, #4129.
- `includes/dompdf/…/FontLib/BinaryStream.php` 4: float to int cast; the library stays unpatched (`core/dompdf-embedding.md`); left.
- `includes/designer_screen.php` 4: headers already sent, after warnings `get_order_preview.php` printed (the iyzico installment table): gone, #4052.
- `add_comment.php` 4: undefined `$file_size`: gone, #4362.
- `includes/forms/verified_shipping_address_form.php` 4: array to string conversion (`?state_id[]=1`): done, EL5 #10439 (both address screens drop array fields).
- `view_submitted_forms.php` 4: done, EL5 #10304.
- `delete_users.php` 2: gone, #5644.
- `add_page.php` 2: gone, #6533.
- `includes/fn/seo.php` 1: gone.
- `view_folder_and_files_f.php` 2: fatal, `PG_FUNCTIONS_DIR` undefined on a direct request: done, #10112 (issue #10052).
- `delete_fields.php` 2: done, EL5 #10304.
- `mysqldump.php` 2: fopen() of an over-long backup file name: gone, #6012 (a typed name is cut to 100 characters).
- `chat.php` 2: array to string conversion: gone, #6010. `view_returns.php` 2 and `view_return.php` 2: the same, gone, #6578.
- `includes/phpmailer/src/PHPMailer.php` 1: openssl_sign() with a DKIM key that is not a private key: a setting, not code; left.
