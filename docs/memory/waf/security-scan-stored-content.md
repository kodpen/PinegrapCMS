---
name: security-scan-stored-content
description: Security Scan stored content: script, hidden frame or redirect from an unknown host in pages, regions, styles, settings; known hosts; the resumable pass
module: security_scan_content
---

## Which rules does it run?
- `content_script_host` (high): a `<script src>`, or an address ending in `.js` given to `.src =` in script code, on an unknown host.
- `content_iframe_hidden` (high): an `<iframe>` on an unknown host that is hidden: the `hidden` attribute, width or height 0 or 1, `display:none`, `visibility:hidden`, `opacity:0`, `left`/`top` of -100 or less. A visible map or video frame is no finding.
- `content_redirect` (critical): a `<meta http-equiv="refresh">` URL, or `location =`, `location.href =`, `location.replace()` / `.assign()` in script code, to an unknown host.
- A link or an `onclick` is never a finding: following it is a visitor's click. Script code is the bodies of `<script>` elements; a field with no markup at all (custom JavaScript) is read whole.
- JSON and JS string escapes (`\/`, `<`, `\"`) are undone first, so a design's JSON is read like plain code; HTML entities are not (`&lt;script` is text).

## Which host is known?
- The site's `HOSTNAME_SETTING` (and without `www.`), every host source of `waf_csp_policy()` (the software's CDNs, payment and CAPTCHA services, the operator's own policy) and `pg_security_content_builtin_hosts()` (Google, YouTube, Vimeo, social networks, the common CDNs, Clarity, Hotjar, Yandex Metrica). A host covers its subdomains, never its parent.
- A third party the operator added on purpose: Ignore on the finding, or the host in the Security settings' CSP policy.
- Limit: the fingerprint is rule and record, so a new host appearing in an ignored record stays ignored; the detail still names it.

## What does it read?
- `pg_security_content_sources()`: `page` (`page_custom_js`, `page_meta_tags`), `pregion_content`, `cregion_content`, `dregion_code`, `style` (`style_head`, `style_code`, `style_custom_js`), and `config.social_networking_code` as `config:1`. A field a migration added is read only when `pg_schema_has()` finds it. Edit screens per type: `data/content-tables.md`.
- Place `<type>:<id>`; detail `host (field, field), host2 (field)`, hosts sorted.
- Quarantine means nothing for a record: the section offers Ignore only for these kinds.

## How does the scan runner call it?
- `pg_security_content_pass($cursor, $deadline, $now)`: reads batches of 50 per source until the `microtime(true)` deadline, writes each sighting through `pg_security_finding_merge()`, returns `cursor`, `done`, `found`, `read`. The runner stores the cursor and gives it back next tick; null starts a new pass, a broken cursor too (`pg_security_content_cursor()`).
- When the last source is read the pass is done and `pg_security_content_close($started)` moves this pass's kinds not seen since it started: open and ignored to gone, quarantined and gone stay.
- Reasons and "What to do" for the section: `pg_security_content_rule_text($kind)`.
- The joined run `pg_security_scan_run()` calls it after the files and keeps its cursor in the `security_scan` row (`security-scan-section.md`).

Tests: `tests/security_content_test.php`.
