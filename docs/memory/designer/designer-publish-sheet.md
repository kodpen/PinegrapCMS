---
name: designer-publish-sheet
description: The visual designer's Publish sheet: which pages it lists and checks, what Publish selected writes, how an unchecked changed page is held out of the one save, and where the old publishing menu went
---

## What opens the sheet?
- `StyleDesigner.publish()` (`_pgPublishClick()`), bound to the main button in `includes/designer_screen.php`: on a draft tab the main button is Save (`saveAjax()`, the page stays off the site); on a live tab it opens the sheet. The draft tab's `#sd-publish-page` opens it too. Ctrl+S never opens it (`designer-save-shortcut.md`).
- `_pgPublishSheet()` falls back to a plain `saveAjax()` when there is no sheet: a shared component opened alone, a site without drafts, a user who cannot change states (the markup is printed only for full access with `pg_page_draft_ready()`), or `pg_designer_publish.js` missing.

## What does it list and check?
- One row per tab, from `_pgPublishRows()`: state `live`, `draft`, `scheduled` (a draft whose publish time is ahead) or `held` (a variant an open experiment keeps a draft); `changed` = a new page or `!_pgPageUnchanged()` (the save's own rule, shared components included); `locked` = no content rights for the folder. Rows are read once when it opens.
- Checked at opening (`PgDesignerPublish.defaults()`): drafts and changed live pages. Not checked: scheduled pages (checking one publishes it now and drops its time), held and locked rows (their box is disabled).

## What does Publish selected do?
- `PgDesignerPublish.plan()`: `publish` = checked drafts and scheduled pages, `hold` = unchecked changed live pages. Then the bridge calls `_pgSetDraft(publish, false, hold)` (its confirm, marks, rollback and message) or, with no draft checked, `saveAjax({ hold })`. One save either way.
- A held page is sent as `unchanged` (`_pgTabsSerialize()`, the hold read from `opts.hold` into `_pgSaveHold` by `_saveAjaxImpl()`): the server keeps its stored row and writes nothing for it. After the save its baseline, saved name and rename follow-up are left alone (`_pgTabsAfterSave()`), so it stays dirty in the editor. A shared component it shows is still flushed: a component is its own row, written for every page that shows it.
- An unchecked draft is saved as it is, off the site: the save is the whole design; only changes going live can be held back.

## Where did the publishing menu go?
- The chevron menu beside the main button is gone. "Save this page as a draft" and "Take every page of the design off the site" are the sheet's foot buttons (`_pgSetDraft(keys, true)`, which asks first); "Publish this page" and "Publish every page" are rows to check ("Select all").
- Its texts come from `lang()` in hidden `data-sdp-text` spans in the markup: `pg_designer_publish.js` is not scanned for `_sdT()` keys.

Plan step D6 of `plans/designer-performance-and-publish.md` (PR #8009); D7 adds the progress and the per-page result to the sheet.
