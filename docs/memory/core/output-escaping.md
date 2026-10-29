---
name: output-escaping
description: Escaping output: h() for every value in HTML, lang() in inline handlers via json_encode(), a raw & in query strings, lang() {var:N} values unescaped, form labels as text, _sdT(), PHP tags
---

## Which helper escapes what?
- `h($val)` (`htmlspecialchars`, `ENT_QUOTES`, `includes/fn/core.php`) for every value that goes into HTML, attributes included. `null` prints as `''`, so no guard is needed (`request-parameter-arrays.md`).
- Give `h()` an address with a plain `&` between query parameters: it encodes `&` itself, so a hand-written `&amp;` comes out as `&amp;amp;` and the parameter is lost.
- `h()` and `escape_javascript()` are not URL validators; a user-given address after `HOSTNAME`: `platform/redirect-after-hostname.md`.
- In the designer's JavaScript `_sdT()` returns raw text: `esc(_sdT('...'))` wherever it goes into HTML.

## Does lang() escape its {var:N} values?
- No. `lang(array('string' => '... {var:1}', 'vars' => $x))` inserts `$x` as is, and `liveform` prints notices, warnings and errors as raw HTML. A stored name or URL that goes into a notice must be wrapped: `'vars' => h($name)`.
- Symptom fixed in #541: an anonymous OAuth dynamic registration (`pg_oauth_register()`) could plant `<img onerror>` in `client_name`, which ran in the panel when a manager removed or re-enabled the client on `api_settings.php`; the same shape covered device names and webhook URLs there and the ended connection on `connected_apps.php`.

## How does translated text go into an inline handler?
- `lang()` returns raw text, so `onclick="pgConfirm({message:'` + `lang(...)` + `'})"` breaks on the first apostrophe of any language. Pass the texts as `json_encode()` of an options array and wrap the whole handler in `h()` (`pg_user_form_reset_password_button()`, `includes/forms/user_form.php`). Symptom fixed: Edit User's Reset & Send Password did nothing in English ("user's"), while Turkish worked (`tests/user_screens_names_test.php`). Site Log's Delete All Error Logs (`view_log.php`) broke the other way: its Turkish text had an apostrophe, the handler did not compile and the form deleted the error_log files without asking (#3263, `tests/notice_button_names_test.php`).

## Is a form field's label HTML?
- No. `fields.label` is the plain text the field editor's `<input>` accepted (a role-3 user with folder edit rights writes it); only an information field's `information` column is rich text. The panel screens (`add_submitted_form.php`, `edit_submitted_form.php`, `view_fields.php`) and the validation messages (`custom_form.php`, `shopping_cart.php`, `express_order.php`, both submitted-form screens) print it through `h()`. The classic front-end renderers (`includes/fn/content.php`, `get_form_item_view.php`, `get_custom_form_confirmation_screen_content.php`) and the widget renderer print it through `h()` too; nothing escapes it earlier, so `h()` goes where it is printed. Until 2026.11.0 the classic renderers printed it raw, so a role-3 folder editor could plant script in a public form; markup a site typed into a label now shows as text (owner, 2026-10-28; PR #2780, `tests/form_label_escaping_test.php`).

## What about PHP tags in raw markup?
- Markup printed raw into a page (page text, rich product descriptions, imported HTML) goes through `pg_defuse_php_tags()`, because the assembled page may be evaluated as PHP: `php-regions-eval-and-content.md`.

Related: docs/memory/core/security-helpers-and-secret-storage.md
