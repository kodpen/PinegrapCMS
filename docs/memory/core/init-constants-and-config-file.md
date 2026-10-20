---
name: init-constants-and-config-file
description: Constants defined in init.php (CONFIG_FILE_PATH, OUTPUT_PATH, ECOMMERCE, ADS ...) and how data/config.php is written (installer, settings screens, escaping, closing tag).
---

## Which constants does init.php define?
| Constant | Meaning | Use |
|---|---|---|
| `CONFIG_FILE_PATH` | `dirname(__FILE__) . '/data/config.php'` | Config file path |
| `OUTPUT_PATH` | Site root URL | Front-end links |
| `OUTPUT_SOFTWARE_DIRECTORY` | Back-end folder name (usually `software`) | Back-end links |
| `ECOMMERCE` | Is ecommerce active | `ECOMMERCE === true` |
| `ADS` | Is the ad module active | `ADS === true` |
| `SET_ERROR_REPORTING` | Error reporting control | `SET_ERROR_REPORTING === true` |
- `ECOMMERCE` and `ADS` are read from the settings table. Check with `defined('ECOMMERCE') && ECOMMERCE === true`.

## Who writes data/config.php?
- The installer, screen and command line alike, builds the whole file with `pg_config_install_content($field_value, $key)` (`includes/fn/core.php`); a reinstall writes it the same way. `$field_value` returns a form field's value.
- In place: `smtp_settings.php` (SMTP, DKIM, campaign job), `private_label.php` (logo, footer links), `commerce.save.php` (ENCRYPTION_KEY reset), `pg_ca_bundle_config_repair()` (CURL_CA_BUNDLE). They save with `pg_write_config_file()` (temp file + rename, refuses empty text so a failed read never empties the file).
- No screen edits DB_* after installation; `edit_config.php` is gone.

## How does a value go into the file?
- Always through `pg_config_php_string()` / `pg_config_define_line()`: single quotes with `\` and `'` escaped, everything else literal. It reads back byte for byte (quotes, backslashes, a closing tag, `$`).
- Until PR #741 the installer pasted DB and SMTP values between quotes as they were: a password with `'` or a trailing `\` left a config.php that did not parse (site down, and `tools/check_install.sh` still passed because it reads the version with its own connection; it now includes the file). The command line refused such values; it no longer does.
- `update_config_define()` replaces the key's line (`pg_config_define_value_pattern()`, confined to one line) or adds one; `remove_config_define()` drops it.
- `smtp_settings.php` and `private_label.php` save values as typed minus control characters and surrounding space (`private_label.php` through `pg_config_form_value()`, `smtp_settings.php` with the same rule inline); both used to strip `'`, which turned `O'Neil` into `ONeil` (smtp: #741, private label: #800).
- `private_label.php` builds the file with `pg_private_label_config_content()` (`core.php`, fields in `pg_private_label_config_keys()`): empty removes the define, a value equal to the loaded constant leaves the file alone (a hand-written double-quoted line is not duplicated), private label off removes all twelve. Test: `tests/private_label_config_test.php`.
- The panel prints these values with `h()`: `CONTROL_PANEL_STYLESHEET_URL` (`<link href>`), `LOGO_URL` (`output.php` header, `theme_designer.php`), footer links (`pg_account_menu()`).

## Where does a new line go?
- `pg_config_insert_line()`: before the closing tag at the very end only, or after the last line when the file has none; text that does not start with `<?php` is returned unchanged.
- Never `str_replace()` over every closing tag: a value may contain one (a password can), and the new line was cut into that string.
- Trap when writing PHP: the two characters of a closing tag end PHP mode even inside a `//` comment. Write "closing tag" in comments.

Related: docs/memory/core/security-helpers-and-secret-storage.md, platform/install-command-line.md
