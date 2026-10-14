---
name: router-no-functions-php
description: router.php serves get_file.php, robots.txt, sitemap.xml without init.php/functions.php, so lang() is unavailable there; use waf_text() / get_file_text().
---

## Which paths skip functions.php, and why?
- `router.php` sends three things without going through `init.php`: `get_file.php`, `robots.txt`, `sitemap.xml`.
- Deliberate: `functions.php` is megabytes and loading it for every image request is pointless.

## What follows from it?
- On these three paths `lang()` and everything in `functions.php` do not exist. Calling `lang()` there turns a user-facing error message into an empty HTTP 500, hiding exactly the sentence needed for diagnosis.

## What is the pattern for user messages in these files?
- A local text wrapper: `waf.php` -> `waf_text()`, `get_file.php` -> `get_file_text()`.
- Both delegate to `lang()` if `function_exists('lang')`, else return the English text (filling `{var:n}` in array form).
- When adding a new user message to these files, use the wrapper, not `lang()`.
- `get_robots.txt.php` and `get_sitemap.xml.php` call their own `init.php`, so they are outside this restriction.
