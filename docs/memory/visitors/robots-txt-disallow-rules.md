---
name: robots-txt-disallow-rules
description: How noindex pages are written into robots.txt: anchored lines, which User-agent group, private folders, and the no-noindex byte-identical guarantee.
---

## How is a noindex page written to `robots.txt`?
- A rule is never a plain prefix: `Disallow: /ara` would also block `/arac-kiralama`. Write three anchored lines per page: `…$`, `…?`, `…/` (`*` and `$` are defined in the RFC 9309 grammar).

## Which `User-agent` group gets the rules?
- A rule belongs to the `User-agent` line above it.
- If the additional content already has a `User-agent: *` group, rules go into it (`pg_merge_robots_rules_into_catch_all()`), after the LAST of the consecutive `User-agent` lines that open the group. Inserting between them splits the definition; a blank line/comment between does not end the group.
- If it has none, our own group is opened and written BEFORE the additional content. Appended after, rules silently narrow to a trailing `User-agent: Googlebot` block if the text ends with one.
- Avoid opening a second `User-agent: *`. Standard-wise fine (RFC 9309 section 2.2.1 requires merging same-token groups), but a primitive parser that stops at the first matching group misses the operator's `Disallow: /`.

## What must never be written?
- A page in a private folder is NOT written to `robots.txt`: the file is public, writing a login-protected address there publishes it.

## Is output unchanged when nothing is noindex?
- With no noindex page, `robots.txt` is byte-for-byte what it was before the feature.
- The file is assembled by `pg_robots_txt_content()` (includes/fn/ai_crawlers.php); the AI section's Content-Signal line takes the same catch-all route as these rules: docs/memory/visitors/ai-crawlers-llms-txt.md.

## Which related traps should I know?
- Splice `</head>` caveat for non-HTML pages: see docs/memory/visitors/seo-structure-non-html-output.md.
- The WAF never blocks `/robots.txt`: see docs/memory/waf/waf-path-exemption-and-robots.md.

Related: docs/memory/visitors/page-noindex-switch.md
