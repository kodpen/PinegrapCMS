---
name: release-bundle-screen
description: The Bundles screen (page_bundles.php): the list, a bundle's pages with "Changed since", its three dialogs, Remove and Delete, what later steps add
module: page_bundles_screen
---

## Where is it, and who reaches it?
- `page_bundles.php` at the root, built as `page_experiments.php` is: `validate_area_access($user, 'designer')` (roles 0 and 1), a liveform for notices, posts back to itself with `validate_token_field()`.
- Without `id`: the list (name, status badge, pages, publish time, changed) and New bundle (a dialog with the name). With `id`: one bundle, a pages card and a sticky Summary card.
- At phone width nothing scrolls sideways: the list puts the status badge, "Pages: n" and the publish time under the name, a bundle's rows put "New page"/"Replaces …", the status and Remove under the draft's name; the wide columns are `d-none md:d-table-cell` (architect, agent channel message 6918).
- No menu row and no link from Pages yet; the screen is reached from the bell line and the preview's Close. Architect (2026-11-16, agent channel message 6918): the one-line menu follow-up comes after RB3 and RB4; both landed, but the screen still has no Publish (#10557).

## What does one bundle show?
- Each page: the draft's name with its Edit link (`pg_page_edit_url_by_id()`), "New page" or "Replaces <live page>", and its status: "Changed since" (a warning badge whose title explains Replace anyway / Leave out), "Waiting" or "Written to the site".
- Summary: status, new pages, replacements, how many changed since, publish time with who set it ("By hand" when none), who made it, who published it.
- Remove is a button per row reaching one hidden form below the table through its `form` attribute (no form in a table row).

## Which dialogs are there?
- Add a copy of a page: `pg_page_bundle_copy_choices()`, the live visual-design pages not in the bin, without an open experiment and without a replacement waiting in an open bundle.
- Add a draft: `pg_page_bundle_draft_choices()`, visual-design drafts that are no open experiment's variant and not in an open bundle. A draft whose own publish time was cleared gets the notice "Its own publish time was cleared: the bundle publishes it." (B4).
- Bundle settings: name and publish time (`data-pg-datetime data-pg-calendar-inline`, read by `pg_page_bundle_time_from_form()`: empty 0, not a time -1). The popup calendar (410 px) did not fit the dialog on a phone, so it opens in the dialog's flow under the field. A time in the past is refused by `pg_page_bundle_set_time()`.
- The three show only while the bundle is open or scheduled; Delete while open, scheduled or taken back, with a confirm saying the drafts stay in Drafts (B6).

## What does the screen leave to other steps?
- Publish and Take back: RB3 (#9999) built the dialogs and runners in the module but no button here; only the time and the API publish (bug #10557). The Publish sheet's held row (RB2b) is in the editor.
- "Preview the bundle" in the toolbar opens the bundle's preview in a new tab while it holds a waiting draft (`release-bundle-preview.md`).

## Which name does a bundle's page carry?
- `pg_page_bundle_items()` names each page through `pg_page_bundle_page_label()`: a page in the Recycle Bin by its own name, not the parked `pgbin_<id>_` one; a page deleted for good as `#<id>`, never `""`. Every message, dialog and log line reads these names (#10048).
