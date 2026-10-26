---
name: lang-compiled-cache
description: The compiled language cache data/temp/lang_<code>.php that pg_lang_map() reads through opcache: speed, the signature that rebuilds it, atomic write, no writable data/temp, the gate (PR #1933)
---

## What is the compiled cache?
- Since PR #1933 `pg_lang_map()` keeps each language as a PHP array in `data/temp/lang_<code>.php` (`pg_lang_cache_path()`; a code that is not a plain name builds no path). opcache holds the array in shared memory, so a request reads the language without decoding JSON: PHP 8.3 CLI, Turkish (29,686 keys), median of 20: 27 ms decoding the shards, 0.2 ms from opcache's memory, 5.7 ms from opcache's file cache; without opcache about the same as the JSON (21 against 27 ms).
- The file holds `signature` and `map`. The signature (`pg_lang_cache_signature()`) is a hash of every source file's name, mtime and size, so a shard added, removed or written with an older time (an update copying the package's times) rebuilds it; "newer than" alone would miss the last case.
- Written by `pg_lang_cache_write()` through a temporary file and `rename()`, then `opcache_invalidate()` (a server may not check file times). A file that does not parse is rebuilt. `data/temp` missing or not writable: the JSON is decoded each request as before; nothing fails.
- The file starts with the Pinegrap header and the `PG_FUNCTIONS_DIR` gate, so it cannot run as a page (`data/temp/.htaccess` also denies it). Safe to delete; clearing `data/temp` only costs one rebuild.
- A caller naming its own `$local` folder gets the cache only with its own `$temp` (the tests do); the tests' default `pg_lang_map('tr')` writes `pinegrap/data/temp/lang_tr.php` in the checkout, which git ignores.

Related: docs/memory/core/lang-files.md
