---
name: server-config-paths-and-detection
description: server_config.php path semantics: patterns carry no PATH but actions do, folder name measured (retarget), server type detection, X-Powered-By removal.
---

## Does a rule pattern carry PATH?
- The file sits next to the software folder, i.e. in the directory `PATH` points to. An IIS `match` or Apache `RewriteRule` pattern there is already relative to that directory; adding `PATH` makes the pattern `magaza/magaza/data/` and the rule matches **no request**.
- The action is the opposite: the rewrite target resolves against the site root, `PATH` belongs there. nginx `location` is an absolute URI.
- `pg_server_config_path()` and `pg_server_config_software_dir()` exist for this split. Never hardcode the folder name: before 2026.4.4 the subdirectory branch looked for `'/pinegrap/router.php'` and silently did nothing on every renamed install.

## Is the folder name assumed?
- No, it is computed: the file is always `<root>/<software>/includes/server_config.php`, so its own path gives the answer (`pg_server_config_web_root()`, `pg_server_config_software_dir()`). If `SOFTWARE_DIRECTORY` is defined it is authoritative (operator can pin it in `config.php`), but when undefined nothing literal is written: an invented folder name gives a correctly read rule that protects nothing.
- The folder name appears in three places in the rules (router target + `data` and `includes` patterns). If the install is renamed or the file copied from another install, the router rule breaks loudly (404) but the two blocking rules break silently: they keep matching a non-existent folder, the real `<new>/data/` is served, nothing looks wrong, and block detection (by rule name) says "present".
- `pg_server_config_retarget($contents, $server)` converts all three; `stale_path` is NOT a separate comparison, it is the question "does retarget change a single byte?" - so reported and fixable are the same thing. A new rule carrying the folder name goes into retarget too.

## How is the server type detected?
- `SERVER_SOFTWARE` is missing in some CGI setups and the command line. `pg_server_config_detect_server()` checks it first, then the file sitting in the root: `web.config` = IIS world, `.htaccess` = Apache world. Neither -> `unknown`. Assuming Apache unconditionally would write a useless file onto an IIS site and say "protected".

## Why is X-Powered-By dropped in PHP?
- It is PHP's own header. IIS `<customHeaders><remove>` reaches only headers IIS adds; Apache `Header always unset` does reach it. So the header is dropped with `header_remove()` in `router.php` and `init.php`. `expose_php` is in php.ini, not ours on shared hosting.
