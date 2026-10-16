---
name: settings-invalid-vs-error
description: Settings save outcomes: rejected input returns status invalid (no re-read, nothing written, no log); pane sentences are plain text.
---

## How does a save module reject operator mistakes?
- If the fault is the operator's, not the software's, the save module does NOT throw; it marks (`mark_error`). After the save the endpoint asks `check_form_errors()`; if marked it returns `status: 'invalid'` + `errors` and does not say "kaydedildi".
- The pane is NOT re-read in this case, deliberately: the marks are on the form, but repainting from stored data would take away everything the operator just typed, which is exactly what they need in front of them to fix it. The dialog stays dirty, the fields stay as they are, the sentences show at the top of the box.

## What must a rejecting module guarantee?
- A module that marks writes NOTHING, for the whole category. If the MailChimp key is rejected, the organization name is not written either: a half-saved category is one Save button reporting two different things. The module validates BEFORE the `UPDATE` and `return`s if anything was marked.
- A rejected save does not log "changed": `pg_settings_apply()` put `log_activity()` behind the `check_form_errors()` gate.
- Network validation happens inside the save, not in a separate button: the MailChimp key asks Mailchimp for list and store and creates the store there if needed, all inside Save. The dialog's save is AJAX anyway; seconds do not matter.

## Why must sentences going to the dialog be plain text?
- `pg_settings_pane_words()` passes every notice and error through `strip_tags` + `html_entity_decode`. The notice box writes TEXT (`.text()`): the sentences come from save modules, tools that pass through here and services like Mailchimp through them; none must enter the page as markup.
- Tags are dropped, not shown (a notice carrying `<textarea>` would embed the DNS record inside the tag). Entities are decoded because a message escaped for a screen arrives as `=&gt;` and there is no HTML left to escape for.

## What if a field arrives as a list (`hostname[]=x`)?
- The whole save is refused before the module runs: `pg_settings_refuse_lists()` (`includes/settings/registry.php`), called by `pg_settings_apply()` and by the page path in `screen.php`, marks each such field; the dialog gets `status: 'invalid'`, nothing of the category is written.
- Not "not given": a module writes an empty value for a field it does not find, so the host name or a key would be blanked.
- Fields the cards draw as lists are named under the category's `lists` in the registry with their depth (`job_dispatch_job` 1, `erp_fx_currencies` 1, `languages` 2); `fake_user` (page-path decoy) always. A new list field goes there too, or every save of its category is refused; `tests/request_unexpected_lists_test.php` checks it.
- SMTP Settings and Private Label refuse the same way with `pg_request_unexpected_lists($_POST)` (error, redirect, config.php untouched).
- So do Application Access (`scope` 1), Marketplaces (`attribute` 1) and the Workspace settings (`workdays`, `members`, `leads`, `primary`, `mcp_tool` 1; `people` 3, each row again with `workdays` 1), before the action is read; the same test checks every list field they draw is named. Why (PR #NNN): arrays 500'd, `app_id[]`/`account_id[]`/`department_id[]` changed or deleted record 1, `name[]` saved a department "Array".
- Why (PR #483): 389 fields posted as lists: 190 answered 500, 15 stored "Array" (host name, subscription key, firewall lists, Mailchimp keys, ERP tax names), others ticked a box or removed a language. SMTP: every field 500. Private Label logged "Undefined array key" on every save with the box off; with it on, every panel page warned about `$output_software_distributor_link` (`includes/fn/output.php`).

Related: docs/memory/ui/settings-card-own-button.md
