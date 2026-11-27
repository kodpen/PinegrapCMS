---
name: designer-new-version-steps
description: Steps NV1 to NV5 of plan designer-new-version: the server call, "Save as a new version" and its tab, Publish from the sheet, the phone and Compare, the Bundles menu row and the close-out
---

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- NV1 (S, new module `includes/fn/page_versions.php`): the server.
  Start: for a live designed page, make the bundle "New version of <page name>" and its copy with `pg_page_bundle_create()` and `pg_page_bundle_add_copy()`, then save the tab state sent with the call onto the copy as a draft. A failure after the bundle was made removes it again.
  Refused with its reason: no rights, an open experiment on the page, the page not on the site. A page that has a version answers with that draft, not a second one.
  The shape rule of the plan as a pure function with its test; Discard: the item removed, the emptied bundle deleted, the draft to the Recycle Bin. The editor's page load names a version's draft and its live page.
- NV2 (M, a designer part): the door and the tab.
  "Save as a new version" is a row of a live page's tab menu, a row of the command palette and a foot button of the Publish sheet beside "Save this page as a draft", with one line under it: "The page on the site stays as it is."
  Pressed, the changes of that tab go to the new draft, its tab opens selected and the live tab goes back to what is on the site. Where a version exists the row reads "Open the new version" and selects its tab.
  The draft's tab carries the pill "New version" with the live page's name in its tip; its tab menu gets "Discard this version", which asks first.
- NV3 (M): Publish. A new version is no held row: its row of the Publish sheet reads "Replaces <page>" and can be checked. Publish selected publishes its bundle with `pg_page_bundle_publish()`.
  When the live page changed since the version was started the sheet asks "Replace anyway" or "Cancel" in the row's place. The result view lists the page as on the site; the draft's tab closes and the live tab shows the new state.
  The draft tab's "Publish" button opens the sheet; a held bundle's draft stays held.
- NV4 (S, after #11968): the phone and Compare.
  "Preview on a phone" asked on a live tab with unpublished changes says the phone shows saved drafts and offers "Save as a new version". On a version's tab "Compare with the live page" compares with the page it replaces.
- NV5 (S): the Bundles screen gets its row in the panel menu, shown to who may open it (the follow-up of `designer/release-bundle-screen.md`).
  Close-out: `designer/designer-new-version.md` written; `page-drafts.md`, `designer-publish-sheet.md`, `designer-publish-sheet-held-row.md`, `designer-tab-states.md`, `designer-preview-menu.md` and `release-bundle.md` corrected; both plan files deleted.

## What is verified?
- NV1: unit tests of the shape rule, the refusals and Discard's order; the call run twice on a sandbox for one page gives one draft.
- NV2 to NV4 try the flow in a browser on a sandbox at 1440 and 390 px, panel light and dark: no unit test reaches the tab moves. The PR says so and carries the numbers `designer/designer-light-and-simple.md` asks for.

Related: docs/memory/plans/designer-new-version.md
