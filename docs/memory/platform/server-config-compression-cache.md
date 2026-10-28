---
name: server-config-compression-cache
description: Web-root server rules for compression and browser caching (PR #2848): static files only, a week, IIS clientCache inside the one staticContent, nginx static location after the deny rules
---

## What do the compression and cache blocks write? (PR #2848)
- `compression` and `cache`, both `recommended`, on all three servers; static files only. Pages carry CSRF tokens (BREACH), and a compressed PHP answer is held until the buffer fills (installer progress, server-sent events). Lifetime a week, as `get_file.php`: library files carry no `?v=` stamp.
- Apache (`top`): `# pg-compression` (mod_deflate) and `# pg-static-cache` (mod_expires), each inside a `<FilesMatch>` on static extensions. A request rewritten to `router.php` is matched as `router.php`, so PHP output, `get_file.php` included, keeps its own headers. The operator's own `SetOutputFilter DEFLATE` / `ExpiresActive On` count as present.
- IIS: `<urlCompression doStaticCompression="true" doDynamicCompression="false"/>` (`web-server`); `<httpCompression>` is locked at server level, never written. `<clientCache>` goes into the existing `<staticContent>` through the `static-content` anchor (before `</staticContent>`, opens a self-closing one, creates one when absent); a second `<staticContent>` is a 500.19. It is listed after `charset`, whose detect is `<staticContent>`.
- nginx: a `location ~* ^<path>.+\.(css|js|…)$` after the deny rules with `gzip on` (never server-wide: nginx always compresses text/html once gzip is on), `expires 7d` and `try_files $uri …/router.php` (uploaded files are served at root addresses through the router). Mirrored in `docker/nginx.conf`.
- Every existing site shows these two as missing (yellow) in System Status until Fix is pressed. Tests: `tests/server_config_blocks_test.php`.

Related: docs/memory/platform/server-config-web-root-rules.md
