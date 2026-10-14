---
name: json-endpoint-rules
description: Rules for endpoints that speak JSON: rejections and CSRF failures must be JSON, and pg_post_body_discarded() must not be used on a JSON body.
---

## What must a JSON-speaking endpoint return on rejection?
- JSON. `validate_area_access()` prints an HTML error page. The same mistake was made in three places (image-save endpoint, CSRF, designer API gate): a caller expecting JSON gets "unexpected token <" and the operator can be told nothing.

## How is a CSRF failure shown?
- In the requested shape. `validate_token_field()` prints HTML via `output_error()` for an expired session. A JSON endpoint applies the same rule itself (session token comparison) and returns 403 + JSON.

## Can I use `pg_post_body_discarded()` on a JSON body?
- No. It treats "`$_POST` empty + `Content-Length` non-empty" as a discarded body: right for a form post, ALWAYS true for a JSON body because PHP does not fill `$_POST` from JSON (a tiny PNG returned 413).
- Look at the raw stream instead: `php://input` is truly empty only when the body was really discarded.

## Does `api.php` read a form body?
- `api.php` reads the body as JSON; an endpoint carrying files (multipart) is therefore a separate script (e.g. `designer_import.php`).

Related: docs/memory/designer/image-editor-tool.md
