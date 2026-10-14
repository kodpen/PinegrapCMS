---
name: waf-csp-builtin-and-reporter
description: Built-in CSP policy (learning mode), waf_csp_policy() allowlist rule for new panel external resources, standalone csp_report.php collector and its storage.
---

## What is the built-in CSP?
- Written to LEARN: inline code and `'unsafe-eval'` are allowed, the software's own CDNs (jsDelivr, jQuery, DataTables, CodeMirror, Google Fonts, Cloudflare beacons) are listed, every other third party is a violation.
- If you add a new external resource to the panel, ALSO add it to `waf_csp_policy()`, or every panel page generates reports.
- An operator policy replaces the built-in entirely; CR/LF are folded; `report-uri` is added if absent.

## How does the collector `csp_report.php` work?
- It is standalone: no init / DB / session, only waf.php; 16 KB body, GET 405, returns 204.
- Aggregates into a file on the (origin, directive, path) triple: `data/temp/csp_reports.json`, 500 records / 14 days.
- It does not pass through the router, so it does not enter the sensitive rate limit; if it did, a violating page would make the visitor's next POST get 429.
- It is NOT written to `waf_log`: a per-visitor row cap would push real events out.

Related: docs/memory/waf/waf-security-headers-hsts.md
