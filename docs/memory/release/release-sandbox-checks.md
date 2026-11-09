---
name: release-sandbox-checks
description: What no running site has confirmed yet and a sandbox pass (tools/setup_sandbox.sh) checks before 2026.11.0 is released: the 2026-10-19 security fixes on other servers, InnoDB search, list speed
---

## What needs a sandbox pass before the release?
- From the 2026-10-19 security audit: #552 (backup extract and upload names, sizes, zip bomb caps) on IIS and nginx; #556's payment gateway returns with the re-ordered cookie re-issue (SameSite, strict mode, token renewed at sign-in).
- Already checked in the sandbox: #551 (`core/session-token-binding.md`); #550's designer text (a manager's hand-made `<?php` is stored as `&lt;?php`, not run); #556's cookie flags, strict mode and sign-in token renewal.

## What was never checked on a running site?
- Search results after `search_items` moved to InnoDB (2026.4.8; the engine differences are in the comment of `upgrade_2026_4_8_innodb_search()`), list screen times with large data, and queries per page after the 2026-10-08 audit's refactors.
