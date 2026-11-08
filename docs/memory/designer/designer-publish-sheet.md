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

## What does the sheet show during and after the save?
- Publish selected keeps the sheet open: the button turns into a spinner and "Publishing…", the rows and the other actions are disabled (`busy()`). The bridge call is `publish(keys, hold, report)`.
- The editor hands the reply to `report` through `opts.reportSaved` of `saveAjax()` (from `_pgSetDraft(keys, false, hold, report)` or the plain save). It returns whether the sheet was open to show it; only when it was not does the success toast appear. Warnings and notes still come as toasts.
- `PgDesignerPublish.outcome(rows, reply.pages, hold)` groups the pages: on the site now, stayed drafts, not saved (held by the sheet, or another user's page, which comes back without `draft`). The result view (`data-sdp-result`) lists them and Done closes the sheet; the next opening starts from the pick view.
- A save that did not run (shared components loading, view mode, nothing to publish, a server or network error) calls `report(null)`: the sheet gives the pick back; the editor already said why.
- Take off the site still closes the sheet first: it asks in a confirm that must not sit under the sheet.

Plan steps D6 (PR #8009) and D7 of `plans/designer-performance-and-publish.md`.
