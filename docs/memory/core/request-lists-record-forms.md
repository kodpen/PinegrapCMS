---
name: request-lists-record-forms
description: pg_request_refuse_lists(): nine record forms (ad, ad region, auto dialog, contact group, container, key code, login region, menu, product attribute) refuse a field posted as a list
---

## What does pg_request_refuse_lists() do?
- `pg_request_refuse_lists($liveform, $screen, $lists = array())` (`includes/fn/core.php`) names the posted fields that carry a list the form does not draw (`pg_request_unexpected_lists()`). For any, it adds the liveform error "Sorry, the value you entered for {names} is not valid." and goes back to `$screen` (below the software directory, with its query string) through `go()`. It returns when every field fits.
- It is called right after `validate_token_field()`, before `add_fields_to_session()` or any field is read: the screen redraws from the session, so a list must not reach it.
- `$lists` names the fields the form draws as lists, with their levels of keys, as for `pg_request_unexpected_lists()`.

## Which screens call it?
- `add_` and `edit_` of `ad`, `ad_region`, `auto_dialog`, `contact_group`, `container`, `key_code`, `login_region`, `menu` and `product_attribute`; their shared forms are `includes/forms/<record>_form.php`.
- None of these forms draws a list, so the screens pass no `$lists`. The product attribute options travel as JSON in the hidden `options` field.
- The edit screens go back to `?id=` with the id the screen checked (`$id`). Edit Ad and Edit Ad Region keep `send_to`; Edit Menu keeps `from` and `send_to`.

## Why refuse?
- Why (PR #11095): a field posted as a list (`name[]=x`) logged "Array to string conversion" and saved "Array" as the name of a contact group, login region or menu. Posting every create and edit form with each field as a list found it.
- Refused, not read as not given: these saves write the fields they read, so a field taken as not given would be saved blank. The settings saves refuse a list for the same reason (`ui/settings-invalid-vs-error.md`).
- A list field added to one of these forms needs its name in the screen's call, or every save of that form is refused.

## Tested?
- `tests/request_refuse_lists_test.php`: the helper returns when every field fits; each screen calls it after the token and before its first read or session write, going back to itself; the screens and their form files draw no `name="x[`.
- Where a list counts as not given instead: `core/request-parameter-arrays.md`.
