---
name: waf-subresource-sign-in-fetches
description: A sub-resource fetch (image, style, script, font, media) of a protected file answers 403, never the sign-in redirect; such a fetch of a sign-in screen is not counted as sensitive (#1989)
---

## What happened?
- #1989 (2026-10-26): a page's submitted-form images (`files.attachment = 1`, their record shown by no public view) were each sent by `get_file.php` to `registration_entrance.php?send_to=`. The browser follows a redirect for an `<img>` like any other, so one page view made 36 GETs of the sign-in screen in a second; the sensitive limit (30/min) refused the rest with 429, each refusal registered an offence, and the fifth banned the reader's address for an hour. The site owner was the reader.

## How is a sub-resource fetch recognised?
- `waf_fetch_is_subresource($method, $dest, $accept)` (waf.php, pure): GET or HEAD whose `Sec-Fetch-Dest` is image, style, script, font, audio, video, track, manifest, a worker or xslt; without that header (Safari before 16.4) the first media type of `Accept` of kind `image/`, `text/css`, `video/`, `audio/` or `font/`. A document, frame, embed, object or `empty` (fetch, XHR) is not one, nor is a wildcard `Accept` alone or a POST. In doubt the request counts as the visitor's own: the answer only ever spares a refusal.

## What does get_file.php do with one?
- Every sign-in redirect (private, guest, registration and membership folders, the attachment refusal) goes through `get_file_sign_in_redirect($screen)`: a sub-resource fetch is answered `403` with the text "Sorry, you need to sign in to view this file." (`output_error()`), a page opening is redirected with `send_to` as before. `router.php` loads `waf.php` before it dispatches `get_file.php`; without the function the helper redirects as before. `tests/waf_subresource_test.php` holds the file to the helper.
- Not changed: which files are refused. A submitted-form file whose record no view shows stays refused (plan question Q1).

## What does the firewall do with one?
- `waf_sensitive_request_counts($method, $script, $dest, $accept)` (pure) decides what `waf_is_sensitive_request()` answers, for the sensitive limit and the in-flight cap alike: every POST counts, every GET of a sensitive script counts, except a sub-resource fetch of a sign-in screen (`waf_sign_in_screens()`: `index.php`, `registration_entrance.php`, `membership_entrance.php`). A GET of those draws a form and does nothing else, what the form sends is a POST, and the global limit still applies, so an attacker claiming `Sec-Fetch-Dest: image` gains nothing; a reset link or a cart action fetched as an image still counts.

Related: docs/memory/waf/waf-inflight-request-cap.md, docs/memory/plans/waf-legit-traffic-auto-ban.md
