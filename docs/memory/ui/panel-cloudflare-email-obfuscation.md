---
name: panel-cloudflare-email-obfuscation
description: Panel body wrapped in <!--email_off--> so Cloudflare Email Obfuscation does not show staff "[email protected]"; which functions, what stays protected, the Cloudflare-side rule
---

## What was the symptom?
- Behind Cloudflare with Scrape Shield › Email Address Obfuscation on, panel screens (for example `mail_queue.php`) showed recipients as `[email protected]`. Cloudflare rewrites every address in an HTML response into a placeholder plus a decoding script; what the script does not reach (cells DataTables draws again, `title` attributes, text copied into inputs or JS) stays a placeholder.

## What does the panel do now?
- PR #1208 (2026.11.0).
- `includes/fn/output.php`: `pg_output_email_off_open()` / `pg_output_email_off_close()` return `<!--email_off-->` / `<!--/email_off-->`, the exemption Cloudflare documents. `output_header()` and `output_header_secure()` print the opening comment right after `<body …>`; `output_footer()` and `output_footer_secure()` print the closing one right before `</body>`. So the whole panel body is exempt, the designer screen included (it uses the panel header).
- Test: `tests/panel_email_off_test.php` (markers, secure footer output, the four functions read from source).

## What keeps the protection?
- Pages built in the designer (`get_page_content()`), e-mail bodies and anything that does not go through these four functions: visitors' pages keep Cloudflare's obfuscation.
- `output_header_secure()` is also the frame of a few software screens a visitor can reach (default login and registration screens in `includes/fn/forms.php`, `mfa.php`, `set_password.php`, `device_limit.php`, the OAuth consent screen, `router.php`'s error page, the installer). They show no public addresses to protect, so they are exempt too.
- `get_login.php`, `install/index.php` and `router.php` close their page themselves, without `output_footer_secure()`: there the opening comment has no closing one and the exemption runs to the end of the page, which is what is wanted.

## What is the Cloudflare-side alternative?
- A Configuration Rule in the Cloudflare dashboard (Rules › Configuration Rules): when URI Path starts with `/pinegrap/` (the software directory), set Email Obfuscation Off. It covers every panel response, including JSON and HTML fragments the comments cannot wrap, and leaves visitor pages protected. The operator sets it; the software cannot.
- Turning Email Address Obfuscation off for the whole zone also works but removes the protection from the public site.

## How was it verified?
- The test above. No instance behind Cloudflare was available: the rewrite itself was not observed before or after.
