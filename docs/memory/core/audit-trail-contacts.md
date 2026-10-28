---
name: audit-trail-contacts
description: Contacts in audit_log (#2170, #2246, #2620, #2643): which panel, API, import, İYS, Workspace, storefront, job, merge paths write a line, the label, erasure
---

## Which paths write a contact line?
- Type `contact`, table `contacts` in `pg_audit_tables()` (`user` column left out), PR #2170. Before #2170 no contact change was traced.
- Panel: `add_contact.php` (create), `edit_contact.php` (save; delete), `edit_contacts.php` opt-in, opt-out, delete (a line per contact; none if already in that state).
- Same-address opt-in sync of `add_contact.php` and `edit_contact.php` (`UPDATE … WHERE email_address`): `pg_audit_fetch_by('contacts', 'email_address', …)` before, the edited or new contact taken out (its own line covers it), `pg_audit_record_many()` after; none for a contact already in that state (#2246).
- `import_contacts.php`: one `create` line per contact made, after the import's own opt-out of a new contact whose address is opted out elsewhere (one line, the final row); batched by 200 through `pg_audit_created_rows()` + `pg_audit_record_many()` (PR #2246).
- İYS sync (`includes/iys/client.php`): `pg_iys_push()` (`iys_email_status` after an accepted push) and `pg_iys_apply_change()` (refusal opt-out and status, one line per contact with both) by address as above. `pg_iys_run()` runs under `pg_audit_as_job(true)`, so the source is `job` also when a manager starts `iys_job.php` from the panel (PR #2246).
- API `customers.php`: create, update, delete, each after `api_dry_run_stop()`.
- Workspace: `ws_change_audit_target()` maps contact to `contacts`; its writer deletes for good, so a delete is `delete` (`core/audit-trail-workspace.md`). The kind's registry `audit` is `contacts`.
- The screen links to `edit_contact.php?id=`.

## What is the label?
- The e-mail address first, then last name, first name, company: erasure also finds the lines of a contact deleted before the erasure by the address.

- Storefront (PR #2620), source `site` (`pg_audit_as_site()`; the member, user 0 for a guest): sign-up (`auth.php` both paths, `signin.php`), profile (`my_account_profile.php`, `affiliate_sign_up_form.php`), checkout (`billing_information.php`, `express_order.php`, `submit_order.php` with membership ids and expiry), unsubscribe (`email_preferences.php`, one-click and form), through `pg_audit_site_record*()`. A contact the checkout makes empty and fills is one `create` line. Pinned by `tests/contact_audit_site_test.php`.
- Merge (#2620): a `delete` line per contact merged away and one `merge` line for the one that stays, `merged_ids` in its after side (`pg_contact_merge_audit()`).

- Jobs (#2643): `membership_job.php` (`warning_expiration_date`), `recurring_payment_job.php` (expiry when a PayPal profile ends), under `pg_audit_as_job(true)`.
- `pg_checkout_fill_contact_tax()` (#2643): a `site` line when the card took the number; `$audit = false` from billing and express order for a contact they made (its `create` line follows).

## What is not hooked, and why?
- `custom_form.php` (contact create and fill): left out of #2643, the collections C3b step changes the file; hook it with `pg_audit_site_record*()`.
- `pg_fs_confirm()` (form_subscription.php) writes only `contacts.timestamp`, dropped by `pg_audit_volatile()`; groups are not in the row.
- `includes/forms/contact_form.php`: panel form helpers; its sync writes lines (#2246).

## What does erasure do with them?
- `pg_privacy_erase_scope()` takes contact lines by the person's contact ids and by label among their addresses. `pg_privacy_erase_audit_fields()['contact']`: names (first name → "Deleted customer"), company, job fields, both addresses, phones, faxes, address → person token, website, description, member id, affiliate name, tax number and office, image. Countries, opt-in and lead source stay. The label is cleared. Owner decision 2026-10-26 (#1706): actor, time and action stay.

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/personal-data-erasure.md, docs/memory/plans/workspace-crm-seams.md
