---
name: error-reporting
description: How PHP error_reporting is set in init.php and router.php via SET_ERROR_REPORTING, and why E_DEPRECATED is not overridable from config.
---

## How is error reporting configured?
In `init.php` and `router.php`:
```php
if (!defined('SET_ERROR_REPORTING') or SET_ERROR_REPORTING) {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
```
- Defining `SET_ERROR_REPORTING` as false in config skips the override.

## Can config override E_DEPRECATED?
- No. `E_DEPRECATED` is a built-in constant in PHP 7+ and cannot be overridden from config (the old override block was removed).
