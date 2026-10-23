---
name: page-messages-defuse
description: Liveform messages (errors, notices, warnings) are HTML printed into the page before the page eval and marker expansion; pg_defuse_page_message() on store and on print, and the escaped upload refusal
---

## Why do messages need their own defence?
- A liveform message is HTML (some carry a link). The visual designer's messages node (`_render_content_html()` case `messages`, `includes/fn/designer.php`; widgets add one through `_pg_inject_messages_node()`) and the messages placeholder of `get_page_content.php` print it into the page BEFORE the page eval, `_expand_custom_php()` and the region tag passes (php-regions-eval-and-content.md).
- A message can hold text a visitor sent: `lang()` puts `{var:N}` values in as they are. Until 2026.11.0 the refused upload sentence named the file as sent and both printers took messages as they were, so a visitor's text could reach the eval.

## What does pg_defuse_page_message() do?
- `includes/fn/core.php`, beside `pg_defuse_php_tags()`: `<?` → `&lt;?`, `<!--pg-` (any case) → `<!-- pg-`, and the bracket of the region tags (`cregion`, `pregion`, `dregion`, `menu`, `menu_sequence`, `system`, `login`, `ad`, `cart`, `tcloud`, `pdf`, `mobile_switch`, `if`, `else`, `php`; the list `pg_tr_sanitize_incoming()` strips) → `&lt;`. Words stay on screen; links and other markup are untouched.

## Where is it called?
- On store: `liveform::defuse_message()` in `mark_error()`, `add_error()`, `add_notice()`, `add_warning()` and `validate_required_field()`, so `output_errors()`, `output_notices()` and `get_errors()` read defused text too. It checks `function_exists()` because the class can load without the function modules.
- On print: each error, notice and warning line in the two printers above, so a message written into the session some other way is covered as well.

## What must a new message do?
- Text a visitor or user typed or sent goes into a message escaped; a message is HTML, not text. The four liveform doors that refuse an upload (`add_comment.php`, `custom_form.php`, `add_submitted_form.php`, `edit_submitted_form.php`) escape the file name with `htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` (the whole message is not wrapped in `h()`: a field's inline feedback escapes again, and a name that is not valid UTF-8 would empty the sentence); the panel doors that print the sentence with `h()` themselves or send it as JSON keep the plain sentence.
- A new place that prints session messages before the eval calls `pg_defuse_page_message()` per line.

## Where is a message shown as text, not as the alert HTML?
- A field's inline feedback: `liveform::output_field()` and the `window.__pgFieldErrors` JS map. Both read `pg_liveform_message_text()` (`core.php`), then escape for their own context, so the HTML of a message is shown as its text and an escaped name is not escaped twice.
- `pg_liveform_message_text()` gives what the alert shows: tags, comments, `<!…>` and `<?…>` go, `<br>` and block tags become a space, entities are resolved once. A `<` that starts no tag ("must be <5", "a <= b") stays; `strip_tags()` dropped the rest of the sentence after it. One pass: a tag inside a comment or a quoted attribute belongs to it. An unclosed quote or comment runs to the end, so the scan is linear (an unclosed quote after many tags once took seconds); `strip_tags()` is the fallback when PCRE hits a limit (#1283).

## Why does add_fields_to_session() skip some keys?
- It copies every `$_REQUEST` key into the form's session entry as a field value. `notices`, `warnings`, `__prefill` and `_form_error_*` name that entry's own buckets, so a posted field by one of those names would sit in a message list without `add_notice()`/`add_warning()`'s defuse; those keys are skipped.

## Tests
- `tests/page_messages_defuse_test.php`: the function, the liveform setters, the designer node, a source scan of the two printers (three defused lines each) and of the four upload doors.
