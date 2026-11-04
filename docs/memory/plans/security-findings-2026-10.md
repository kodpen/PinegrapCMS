---
name: security-findings-2026-10
description: 2026-10-19 security audit: fixed by that day's PRs and #713; items 2-8 decided by the owner 2026-10-28 (labels, CVV, escape_like, sign-in message, gateway secrets, ERP host, rich text)
---

## Where does this come from?
- Read-only code audit of 2026-10-19 (`development` @ `fd9de204`). SQL injection: none in ~8,800 query sites. Check `workflow/decisions-made.md` before each item; none below is on it.

## What was fixed?
- #541 notice escaping; #550 content-level `<?` defused, importer SSRF/CSRF, `url_to_absolute()`; #551 session bound to its token, remember-me MFA gate; #552 backup extract/upload names and sizes, zip bomb caps; #554 form labels and file names escaped, Cancel targets, download names, `random_int()`, `dregion` hidden from designers; #556 SameSite/strict mode, token renewed at sign-in, mail links from `HOSTNAME_SETTING`, install unlock lockout, int casts, `sql_device_type()`, API `escape_like()`; #713 the installer loads `waf.php`, so that lockout counts. Memory: `core/php-regions-eval-and-content.md`, `core/session-token-binding.md`, `core/security-helpers-and-secret-storage.md`, `platform/backup-upload-extraction.md`.

## What is still open? (one PR each)
- Done: 2 #2780, 3 #2802, 4 #2756, 5 #2755, 6 #2791, 7 #2748, 8 #2732.
1. **PHP-region eval re-ordering** (owner 2026-10-19: no change now; `core/php-regions-eval-and-content.md`): evaluate designer code only (style code, designer regions) before text and widgets are expanded, so the eval never sees expanded content. #550 is the defence layer, not the architecture.

## What was never verified on a running site?
- #552 on IIS/nginx, #556's gateway returns with the re-ordered cookie re-issue. A sandbox pass (`tools/setup_sandbox.sh`) before the release closes these.
- Sandbox-checked: #551 (`core/session-token-binding.md`); #550's designer text (a manager's hand-made `<?php` is stored as `&lt;?php`, not run); #556's cookie flags, strict mode, sign-in token renewal.
