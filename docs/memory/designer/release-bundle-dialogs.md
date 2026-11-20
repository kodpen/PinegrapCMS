---
name: release-bundle-dialogs
description: The publish and take-back dialogs of a release bundle: what they post, the "changed since" choice, Publish disabled until chosen, focus on Cancel, their words
module: page_bundles
---

## Where are they built?
- `pg_page_bundle_publish_dialog($bundle, $check, $url)` and `pg_page_bundle_take_back_dialog($bundle, $check, $url)` return the `<dialog>` (look A rows and pills, Bootstrap 6). They post `action=publish` with `choice[item id]`, or `action=take_back` with `kept[]`, and `bundle_id`.
- No screen prints them yet: RB3 (#9999) built them but `page_bundles.php` has no Publish or Take back (bug #10557). The screen is to print them, open them with `pgUI.dialog`, check the token and call the locked runners of `pg_page_bundle_publish()` / `pg_page_bundle_take_back()`.

## How does the "changed since" choice work?
- A pair of `btn-check` radios per row, "Replace anyway" and "Leave out"; neither comes chosen and the chosen one shows pressed.
- Publish starts disabled while a row is asked, and while a page is locked or refused. `pg_page_bundle_publish_dialog_script()`, printed once a page and listening on the document, enables it once every row has its choice. The server asks again if a choice is missing.

## Where does focus go?
- On Cancel (`autofocus`). A heading cannot take it: `canFocus()` in `pg_ui.js` skips tabindex -1, so the take-back dialog, which has no field, focused itself and drew a ring around the whole box.

## Which words?
- Architect (2026-11-16, agent channel message 7027): the same keys and Turkish as the Bundles screen: Changed since / Sonradan değişti, Replace anyway / Yine de değiştir, Leave out / Dışarıda bırak; take back's title and button share the verb "Geri al" (the button is `lang('Take it back')`; "Take back" is "Geri çek" elsewhere).
- A row's explanation is plain `pg-text-2`, never `pg-list-row-text`, whose two-line clamp cut it on a phone.
