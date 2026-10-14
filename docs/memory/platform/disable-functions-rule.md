---
name: disable-functions-rule
description: Shared hosts remove functions via disable_functions; in PHP 8 they throw Error, so guard with function_exists() and treat their output as optional.
---

## Why must disable-able functions be asked before calling?
- Shared hosts remove functions with `disable_functions`; the list is not ours.
- **In PHP 8 a disabled function is an undefined function:** `function_exists()` returns false, calling it throws `Error`, `@` does not silence it.
- First field crash (2026-09-01): `@disk_free_space()` in the preflight card turned the upgrade screen into a white page on a customer server.

## Which functions were seen disabled in the field?
- `disk_free_space`, `disk_total_space`, `ini_get_all`, `set_time_limit`, `ignore_user_abort`, `getmypid`, `php_uname`, `sys_getloadavg`, `getrusage`, `error_log`, `mail`, `fsockopen`, `symlink`, `chmod`/`chown`, the whole `exec`/`shell_exec`/`proc_*`/`posix_*` family.

## What is the rule?
```php
if (function_exists('set_time_limit')) { @set_time_limit(0); }
$pid = function_exists('getmypid') ? getmypid() : uniqid();
$request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS;
```
- What these functions give is always optional: if missing the answer is "unknown", the flow does not stop.
- No exceptions in the install/upgrade path (`install/index.php`, `includes/migrations/*`): it is the path that runs first on a new server.
- In `runner.php`, `install_log()` wraps `error_log`; the preflight card is wrapped in `catch (Throwable)` so whatever breaks inside it, the upgrade is still offered.
