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

Related: docs/memory/ui/settings-card-own-button.md
