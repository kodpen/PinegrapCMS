---
name: output-escaping
description: Escaping output: h() for every value in HTML, a query string's & passed raw, lang() {var:N} values not escaped, form labels as plain text, _sdT() output unescaped, PHP tags in raw markup.
---

## Which helper escapes what?
- `h($val)` (`htmlspecialchars`, `ENT_QUOTES`, `includes/fn/core.php`) for every value that goes into HTML, attributes included. `null` prints as `''`, so no guard is needed (`request-parameter-arrays.md`).
- Give `h()` an address with a plain `&` between query parameters: it encodes `&` itself, so a hand-written `&amp;` comes out as `&amp;amp;` and the parameter is lost.
- `h()` and `escape_javascript()` are not URL validators; a user-given address after `HOSTNAME`: `platform/redirect-after-hostname.md`.
- In the designer's JavaScript `_sdT()` returns raw text: `esc(_sdT('...'))` wherever it goes into HTML.

## Does lang() escape its {var:N} values?
- No. `lang(array('string' => '... {var:1}', 'vars' => $x))` inserts `$x` as is, and `liveform` prints notices, warnings and errors as raw HTML. A stored name or URL that goes into a notice must be wrapped: `'vars' => h($name)`.
- Symptom fixed in #541: an anonymous OAuth dynamic registration (`pg_oauth_register()`) could plant `<img onerror>` in `client_name`, which ran in the panel when a manager removed or re-enabled the client on `api_settings.php`; the same shape covered device names and webhook URLs there and the ended connection on `connected_apps.php`.

## Is a form field's label HTML?
- No. `fields.label` is the plain text the field editor's `<input>` accepted (a role-3 user with folder edit rights writes it); only an information field's `information` column is rich text. The panel screens (`add_submitted_form.php`, `edit_submitted_form.php`, `view_fields.php`) and the validation messages (`custom_form.php`, `shopping_cart.php`, `express_order.php`, both submitted-form screens) print it through `h()`. The classic front-end renderers (`includes/fn/content.php`, `get_form_item_view.php`, `get_custom_form_confirmation_screen_content.php`) still print it raw pending the owner's decision in `plans/security-findings-2026-10.md`; the widget renderer escapes it.

## What about PHP tags in raw markup?
- Markup printed raw into a page (page text, rich product descriptions, imported HTML) goes through `pg_defuse_php_tags()`, because the assembled page may be evaluated as PHP: `php-regions-eval-and-content.md`.

Related: docs/memory/core/security-helpers-and-secret-storage.md
