---
name: member-email-asked-once
description: Registration and membership activation ask for the email once: email_verify optional, a typed copy must match; starter layouts, templates, classic screens and the starter site changed
---

## Is the address asked twice on sign-up?
- No. `pg_member_register()` and `pg_member_activate()` (`includes/fn/auth.php`) do not require `email_verify` (nor `email_address_verify`, the membership entrance's own name). A second copy that was typed in still has to be the same address (`pg_member_email_repeat_ok()`: absent or empty passes, compared as typed, no case folding), so a layout that keeps the field still catches a typo. Tests: `tests/member_email_test.php` (the two functions run in a PHP process of their own; their cases stop before the first query).
- Why: WCAG 2.2 3.3.7 (redundant entry). Decided 2026-10-19; before, both functions sent the form back with "Please type email address again.".

## Which forms ask once now?
- The registration and membership widgets' starter layouts (`_buildStarterTree()`, `StyleDesigner.starterTree()`): no "Email (again)" control. Built-in templates take these layouts (`'tree' => 'starter'`), so their sign-up pages ask once without a change to the template files.
- Classic screens: both layouts of `get_registration_entrance.php` and `get_membership_entrance.php` (no "Confirm Email" row, no `required` set for it), the generated layouts `includes/templates/registration_entrance.php` and `membership_entrance.php`, and the starter site's own layouts (`data/backups/*_default/layouts/126.php` registration, `982.php` membership; new installs only).
- The designer's Membership Field panel and the widgets' core-control notes call `email_verify` optional (`MEMBER_CORE_FIELDS`).

## What happens on an existing site?
- Nothing in its own design or modified layout is rewritten. A registration or membership widget made earlier keeps its `email_verify` control (with `required` in its markup) until the designer removes it; the server takes the form with or without it. A modified classic layout keeps its input, without the `required` the screen used to add.
- The Google return of the membership entrance still fills both copies (`get_membership_entrance.php`, `_render_system_widget_membership()`) for layouts that keep the field.

## What is still asked twice?
- The password (`password_verify`): a password is not shown, so retyping it is the usual security exception; the designer audit does not look at passwords either.
- The installer's administrator address (`admin_confirm_email_address`): not a site form, left as it is.

Related: docs/memory/designer/member-widgets-handlers-and-forms.md
