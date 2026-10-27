---
name: audit-trail-contacts
description: Contacts in audit_log (PR #2170): which panel, API and Workspace paths write a contact line, the label, the writers not hooked, how erasure clears the lines
---

## Which paths write a contact line?
- Type `contact`, table `contacts` in `pg_audit_tables()` (`user` column left out), PR #2170. Before #2170 no contact change was traced.
- Panel: `add_contact.php` (create), `edit_contact.php` (save, after its same-address opt-in sync; delete), `edit_contacts.php` opt-in, opt-out and delete (one line per contact; a contact already in that state writes none).
- API `customers.php`: create, update, delete, each after `api_dry_run_stop()`.
- Workspace: `ws_change_audit_target()` maps contact to `contacts`; its writer deletes for good, so a delete is `delete` (`core/audit-trail-workspace.md`). The kind's registry `audit` is `contacts`.
- The screen links a line to `edit_contact.php?id=`.

## What is the label?
- The e-mail address first, then last name, first name, company: erasure also finds the lines of a contact deleted before the erasure by the address.

## What is not hooked?
- Storefront sign-up and profile, checkout, `merge_contacts()` (its plan step asks the owner first), imports, İYS sync, unsubscribe, and the multi-row `opt_in` UPDATEs by address (only the edited contact's own line shows the change).

## What does erasure do with them?
- `pg_privacy_erase_scope()` takes contact lines by the person's contact ids and by label among their addresses. `pg_privacy_erase_audit_fields()['contact']`: names (first name → "Deleted customer"), company, job fields, both addresses, phones, faxes, address → person token, website, description, member id, affiliate name, tax number and office, image. Countries, opt-in and lead source stay. The label is cleared. Owner decision 2026-10-26 (#1706): actor, time and action stay.

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/personal-data-erasure.md, docs/memory/plans/workspace-crm-seams.md
