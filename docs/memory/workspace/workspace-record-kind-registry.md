---
name: workspace-record-kind-registry
description: ws_record_kinds() (refs.php): one entry per Workspace record kind (prefixes, right, read scope, change kind, audit table, drawer, customer), the lists it is held to, the drift test
---

## What is it?
- `ws_record_kinds()` in includes/workspace/refs.php: one entry per kind of `ws_record_type_keys()`, in its order. Pure: no database, no reader's rights (what a person may see is still `ws_ref_types()` and each kind's checks).
- Per kind: `prefixes` (words typed after #), `right` (the `ws_viewer()` right of its tag and of its change, '' for every member), `scope` (what `ws_ai_type_scope()` asks; workspace:read when no API scope reads the kind), `change` (its `ws_change_types()` kind, '' for the tag-only ERP documents), `audit` (the `pg_audit_tables()` table its Workspace write is recorded on, '' for none), `quick_edit` (drawer), `customer` (a customer channel can be about it).
- A reader that needs a kind's scope, right or change (CRM summary and history rows, plans/workspace-crm-seams.md) reads it here.

## Do the old lists read it?
- Not yet: `ws_record_type_keys()`, `ws_ref_types()`, `ws_change_types()`, `ws_change_audit_target()`, `ws_quick_edit_types()`, `ws_customer_types()`, `ws_ai_type_scope()` and `pg_audit_tables()` keep their own literals. A new kind is described in the registry first, then written into each list.

## What holds them together?
- tests/workspace_record_kinds_registry_test.php, list by list: a name the list has that the registry lacks, or one the registry gives it that it lacks, fails with the list's name. A difference kept on purpose sits in that list's `except` in `workspace_record_kinds_registry_test_lists()` with its reason; a reason that no longer matches a difference fails as out of date.
- Read from source: the catalogue through `pg_test_ws_change_types()` (its schema probes need a database); the `$tables` map of `ws_change_audit_target()` and the `$scopes` map of `ws_ai_type_scope()` only because they are local variables: both functions are pure, and the test also calls them directly.
- Also checked: the order is `ws_record_type_keys()`'s; the prefixes are `ws_ref_types()`'s; the right alone shows the tag, every other right without it does not; a change is tagged as its kind and asks its right; `ws_change_audit_target()` records the change on `audit`; a drawer kind is changed through the kind of its own name.

## Which differences are kept on purpose?
- Change kinds that are no record kind: stock (a second change on the product row, recorded on products too), channel, task, task_note, note.
- Audit tables that are no record kind: config, api_apps.
- Scopes: user_account is left to the default workspace:read (no API scope reads users); task is in the map (tasks:read) but is no site record.

## Where are the other lists checked?
- Resolve, search, API refs, bulk, events, search scopes, screens: tests/workspace_record_kinds_test.php (workspace-record-kinds.md).
