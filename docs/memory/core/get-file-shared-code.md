---
name: get-file-shared-code
description: get_file.php never loads functions.php: the shared files (authentication.php, image_engine.php, record_files.php), what they may call, and the copies check_copies.php allows.
---

## Why does get_file.php need its own arrangement?
- `router.php` sends file requests to `get_file.php`, which never loads `functions.php` on purpose (a file request should not parse the whole module set). It still has to decide who is signed in, which folders they may see and how to make an image derivative, so that code lives in files both sides include.

## Which files are shared, and what may they call?
- `includes/authentication.php`: sign-in and folder access, one copy (`pg_auth_token_verify()`, `pg_auth_token_revoke()`, `pg_load_user_row()`, `pg_session_sign_in()`, the session token binding, `get_access_control_type()`, `check_view_access()`, `check_edit_access()`, `pg_folder_edit_access()`, `check_private_access()`; the folder functions since 2026.4.8). Contract in its header: only `db()`, `db_value()`, `db_item()`, `db_items()`, `escape()` and PHP itself; no `lang()`, no `output_error()`; anything else behind `function_exists()` (pattern: `pg_auth_token_revoke()`).
- `includes/image_engine.php`: `pg_process_image_file()` (the one place that touches image pixels), `pg_ini_bytes()` and the responsive derivatives. Its functions call only each other and PHP: no `db()`, no `lang()`, no `output_error()`. Reading the operator's settings stays in `includes/fn/image.php` (`pg_image_settings()`); a derivative gets what it needs passed in. Gate: `PG_FUNCTIONS_DIR` or `PG_GET_FILE`.
- `includes/record_files.php`: whether a designed record view or list shows a submitted form's upload (`pg_record_file_designed_access()`). Loaded by `get_file.php` only, on its attachment refusal path; its copies of widget helpers are held to their originals by `tests/record_files_test.php`. Contract: itself, `escape()`, `check_view_access()`, `pg_auth_column_exists()`, PHP (`designer/designer-form-uploads-for-readers.md`).
- Breaking a contract brings back the dependency the file exists to avoid: the function is missing on the `get_file.php` side (a fatal error on a file request), not on the panel.

## Which copies are allowed?
- `get_file.php` keeps 11 deliberate copies, the `$intended` list of `tools/check_copies.php`: `output_error`, `db`, `db_value`, `db_item`, `db_items`, `escape`, `log_activity`, `initialize_user`, `get_request_uri`, `check_if_request_is_secure`, `check_proxy_ssl_headers` (reasons: `docs/_get_file_kopyalar.md`). When you change one of them, change the `get_file.php` copy too.
- CI runs `php tools/check_copies.php`: a function declared in `get_file.php` and again in `functions.php`, `includes/authentication.php` or `includes/fn/*.php` that is not on the list FAILs; a listed name that is no longer duplicated is a WARN.

Related: docs/memory/core/functions-php-split-rules.md, docs/memory/core/session-token-binding.md
