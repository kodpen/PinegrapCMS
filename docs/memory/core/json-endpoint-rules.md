---
name: json-endpoint-rules
description: Endpoints that speak JSON: rejections and CSRF failures in JSON with the right status, api.php reads a JSON body (FormData gets "Invalid action."), no pg_post_body_discarded() on a JSON body.
---

## What must a JSON-speaking endpoint return on rejection?
- JSON, with the right status. `validate_area_access()` prints an HTML error page. The same mistake was made in three places (image-save endpoint, CSRF, designer API gate): a caller expecting JSON gets "unexpected token <" and the operator can be told nothing.

## How is a CSRF failure shown?
- In the requested shape. `validate_token_field()` prints HTML via `output_error()` for an expired session. A JSON endpoint applies the same rule itself (session token comparison with `hash_equals`) and returns 403 + JSON (`ui/settings-modal-endpoint.md` does this).

## Can I use `pg_post_body_discarded()` on a JSON body?
- No. It treats "`$_POST` empty + `Content-Length` non-empty" as a discarded body: right for a form post, ALWAYS true for a JSON body because PHP does not fill `$_POST` from JSON (a tiny PNG returned 413).
- Look at the raw stream instead: `php://input` is truly empty only when the body was really discarded.

## What does a session or token refusal say?
- The way out, in the panel language: a missing session answers "Your session has expired. Reload the page and sign in again." (`panel_login_refusal()` in `api.php`, `code: session`), a token that does not match the session "Your session expired. Reload the page and try again.". Never the bare "Invalid login." / "Invalid token.": the panel toasts `message` as is (#3307, `tests/panel_session_refusal_test.php`). Only a request that sent a wrong user name and password still gets "Invalid login.".
- A barcode scan whose request got no answer says the quantity may already have changed: the UPDATE can run before a dropped connection or a failed page, so a blind re-scan could count twice.

## Does `api.php` read a form body?
- No: `api.php` decodes `php://input` as JSON. A FormData or multipart post decodes to nothing, so it has no action: a designer gets "Invalid action.", a lower role is stopped at the role gate first. An endpoint carrying files (multipart) is therefore a separate script (e.g. `designer_import.php`).
- New `api.php` actions: `panel-action-table.md`.

Related: docs/memory/designer/image-editor-tool.md
