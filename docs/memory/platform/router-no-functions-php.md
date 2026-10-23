---
name: router-no-functions-php
description: What runs before functions.php is loaded (router.php, the firewall's router phase, get_file.php), why lang() is unavailable there, and the waf_text() / get_file_text() wrappers.
---

## Which code runs without functions.php, and why?
- `router.php` handles every address the rewrite rules send it before anything loads `functions.php`: the firewall's router phase (`waf_run('router')`), the short-link answer and the dispatch itself. `get_file.php`, which it requires for files, never loads it at all.
- Deliberate: `functions.php` and its modules are megabytes, and loading them for every image request is pointless.
- `robots.txt`, `sitemap.xml` and `llms.txt` are answered by `get_robots.txt.php`, `get_sitemap.xml.php` and `get_llms.txt.php`, which include `init.php` themselves; the firewall's router phase has already run without it by then.

## What follows from it?
- In that code `lang()` and everything in `functions.php` do not exist. Calling `lang()` there turns a user-facing error message into an empty HTTP 500, hiding exactly the sentence needed for diagnosis. `router_output_error()` loads `functions.php` itself before drawing its page.

## What is the pattern for user messages in these files?
- A local text wrapper: `waf.php` -> `waf_text()`, `get_file.php` -> `get_file_text()`.
- Both delegate to `lang()` if `function_exists('lang')`, else return the English text; `get_file_text()` also fills `{var:n}` in the array form, `waf_text()` takes a plain string only.
- When adding a new user message to these files, use the wrapper, not `lang()`.
