---
name: error-path-missing-methods
description: liveform has add_error() for form-level messages vs mark_error() per field; never call a method that does not exist on error branches (PHP 8 gives a blank HTTP 500).
---

## What happened with `liveform::add_error()`?
- The `liveform` class **had no `add_error()`** yet it was called from nine places in four files: `edit_config.php` (x2), `edit_orders.php`, `import_design_zip.php` (x5), `includes/designer_screen.php`. All produced an **empty-body HTTP 500**.
- All nine share one trait: **code that runs only when something goes wrong.** That is why it went unseen for years, and the impact was in the worst place: instead of "config file could not be opened" the operator saw a blank page.

## What are the rules?
- **Before calling a new message method on a class, verify it exists in the class.** `mark_error($field, $msg)` is per field; the form-level message is `add_error($msg)` and writes to a synthetic field key (`_form_error_0`, `_form_error_1`, ...). `output_errors()` walks fields looking for the `error` flag, so a separate list would be invisible, and `mark_error()` on a single key would keep only the last message.
- **Run the error branch at least once.** The happy path is not enough to call a feature working; the rejection path also produces a screen.
- In PHP 8 an undefined method throws `Error` and `@` does not suppress it: not silent, **invisible** (500 + zero bytes).
