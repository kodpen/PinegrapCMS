---
name: init-constants-and-config-file
description: Constants defined in init.php (CONFIG_FILE_PATH, OUTPUT_PATH, ECOMMERCE, ADS ...) and the config.php editing functions of edit_config.php.
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

## How is the config file handled?
- `parse_config_file()` parses `data/config.php` with a regex (reads the physical file, not the runtime constants).
- `update_config_define($content, $key, $value, $type)` updates a define; `remove_config_define($content, $key, $type)` removes a define from the file.
- Boolean fields are shown with a 3-state select: "Not Selected" (no define) / true / false.
- `edit_config.php` writes nothing when there is no change (check `$config_content === $config_raw`).

Related: docs/memory/core/security-helpers-and-secret-storage.md
