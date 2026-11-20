---
name: release-bundle-dialogs
description: The publish and take-back dialogs of a release bundle: where the Bundles screen shows them, what they post and the screen does with it, the "changed since" choice, focus on Cancel, their words
module: page_bundles
---

## Where are they built?
- `pg_page_bundle_publish_dialog($bundle, $check, $url)` and `pg_page_bundle_take_back_dialog($bundle, $check, $url)` return the `<dialog>` (look A rows and pills, Bootstrap 6). They post `action=publish` with `choice[item id]`, or `action=take_back` with `kept[]`, and `bundle_id`.
- The Bundles screen prints them (#10557, after RB3 #9999 built them unwired): Publish while open, scheduled or partly published ("Publish the rest"), and only while a page waits; Take back while published or partly published (`pg_page_bundle_screen_actions()`). Look A buttons with `data-pg-bundle-dialog` open them through `pgUI.dialog.show()`.

## What does the screen do with their post?
- `validate_token_field()` first, then `pg_page_bundle_publish()` / `pg_page_bundle_take_back()`, both under the bundle's lock, with `choice[]` and `kept[]` cleaned by `pg_page_bundle_screen_choices()` / `pg_page_bundle_screen_kept()`.
- The outcome is a liveform notice or error (`pg_page_bundle_screen_outcome()`): the runner's own error (busy, pages in the way, a failure taken back, partly published) or "The bundle was published: its pages are on the site." / "The bundle was taken back."
- Before a take-back the screen refuses while a page that comes back is open in the editor (`pg_collab_may_edit_page()`), as the API does. Tests: `tests/page_bundles_screen_test.php`.

## How does the "changed since" choice work?
- A pair of `btn-check` radios per row, "Replace anyway" and "Leave out"; neither comes chosen and the chosen one shows pressed.
- Publish starts disabled while a row is asked, and while a page is locked or refused. `pg_page_bundle_publish_dialog_script()`, printed once a page and listening on the document, enables it once every row has its choice. The server asks again if a choice is missing.

## Where does focus go?
- On Cancel (`autofocus`). A heading cannot take it: `canFocus()` in `pg_ui.js` skips tabindex -1, so the take-back dialog, which has no field, focused itself and drew a ring around the whole box.

## Which words?
- Architect (2026-11-16, agent channel message 7027): the same keys and Turkish as the Bundles screen: Changed since / Sonradan değişti, Replace anyway / Yine de değiştir, Leave out / Dışarıda bırak; take back's title, its button and the screen's toolbar button share the verb "Geri al" (all `lang('Take it back')`; "Take back" is "Geri çek" elsewhere).
- A row's explanation is plain `pg-text-2`, never `pg-list-row-text`, whose two-line clamp cut it on a phone.
