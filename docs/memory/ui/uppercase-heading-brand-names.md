---
name: uppercase-heading-brand-names
description: English brand and product names inside text-uppercase titles carry lang="en", or the Turkish panel prints WİNDOWS, MAİLCHİMP, MİCROSOFT (#3613)
---

## What went wrong?
- The panel page is `lang="tr"`, and `text-transform: uppercase` follows the Turkish rule: `i` becomes `İ`. Site Settings card titles showed `CRON JOBS (WİNDOWS)`, `MAİLCHİMP`, `SIGN IN WITH GOOGLE, MİCROSOFT AND APPLE` (owner bug #3613).
- Dropping `text-uppercase` was refused (#3622, `workflow/decisions-made-product-8.md`): keep the class and mark the source-language text.

## How is an English name marked?
- Wrap it in `<span lang="en">…</span>` inside the uppercase element; the browser uppercases that span with the English rule.
- A whole English title: `lang="en"` on the title element itself (`connections.php`, provider labels).
- A title mixing translated words and names: one key with `{var:N}` placeholders, each name passed as a span: `lang(array('string' => 'Sign in with {var:1}, {var:2} and {var:3}', 'vars' => array('<span lang="en">Google</span>', …)))` (`includes/settings/security.php`).
- A name from a variable: `'<span lang="en">' . h($name) . '</span>'` (Cron Jobs title in `includes/settings/maintenance.php`).
- The old full-text keys (`MailChimp`, `Sign in with Google, Microsoft and Apple`) stay: `includes/settings/registry.php` lists them in the settings search.

## What was not covered?
- Only the `lang()` uppercase titles of Site Settings were scanned (three found). Titles built from variables on other screens were not audited.
- Test: `tests/settings_heading_lang_and_range_test.php`.
