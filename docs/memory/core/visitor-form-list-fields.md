---
name: visitor-form-list-fields
description: Visitor form handlers with a field posted as a list: member registration and activation, the reservation and watcher forms' session index, the built-in CAPTCHA answer (PR #11061)
---

## What happens when a visitor form field is posted as a list?
- It counts as not given, as `core/request-parameter-arrays.md` asks. The form answers its own "required" message or refusal, never 500 and never the text "Array".

## Which member and CAPTCHA functions do this?
- Member registration and activation (`pg_member_register()`, `pg_member_activate()` in `includes/fn/auth.php`) call `pg_liveform_single_values()` for their fields first.
- Before (PR #11061): "Array" was stored as a name or password, with "Array to string conversion". In activation, `escape()` of a first name sent as a list stopped the request.
- `pg_form_captcha_check()` reads its key and answer only when they are scalar. An answer sent as a list is a wrong answer on every form that asks the built-in question.

## What about a posted id that names the form's session entry?
- `new liveform($form, $index)` uses `$index` as a session key: never pass a raw `$_POST` value there.
- Call `pg_request_single_values()` for the id and pass `$_POST['id'] ?? 0`. `add_comment.php` and `allow_or_disallow_new_comments.php` do this; so do `reserve_calendar_event.php` and `add_or_remove_watcher.php` since PR #11061.
- Before: a list was a TypeError ("Cannot access offset of type array", HTTP 500), and a missing id logged a warning.
- An unknown event or page falls back to an empty record that reaches the existing refusal (an unpublished event, comments off), so the visitor reads the same text as before.

## How is it checked?
- `tests/visitor_form_handlers_test.php`.
- `tests/member_email_test.php` (`test_member_forms_list_fields_count_as_not_given`).
- `tests/captcha_test.php` (`test_form_captcha_check_list_values_refused`).

Related: docs/memory/core/request-parameter-arrays.md, docs/memory/platform/front-visitor-missing-keys.md
