---
name: designer-new-version-phone-compare
description: A live page's new version with "Preview on a phone" and "Compare with the live page" (NV4): the phone's line on a live tab with changes, its button, Compare against the replaced page
---

## What does "Preview on a phone" say on a live tab with changes?
- The phone shows saved drafts, and a live page's Save would publish its changes. So when the page on screen is on the site and its own tab has unsaved changes, the window's info line (amber) reads "The phone shows saved drafts, not the changes on this page on the site. Save them as a new version to see them there; the page on the site stays as it is." with the button "Save as a new version".
- Where the page already has a version the line ends "This page has a new version: open it to see that draft on the phone." and the button reads "Open the new version".
- Only where the tab's door (`_pgVersionDoorOf()`, `designer-new-version-tab.md`) is `start` or `open`; any other page with changes keeps the plain line with Save. The kind is `_phInfoKind(host)` (pure); the core's `_sdPhoneHost()` gives `version()` (the door, for a page on the site whose own tab is dirty) and `newVersion()`.

## What does the button do?
- The window is modal, so it closes first (`pgUI.dialog.hide(...).then`), then `newVersion()` runs the part `versions`' `start(key)`. `start()` now resolves true once the version's draft is the selected tab (started, existing, or opened) and false otherwise; on true the core opens "Preview on a phone" again, on the draft, whose phone link starts there.

## What does Compare compare on a version's tab?
- `_pgCompareTarget(p, entry)` (pure, beside `_pgVersionDoor()`): a page saved here gives `{ pageId }`; a version's draft (versions block role `version`) gives `{ pageId, livePageId }`, the live page it replaces; a page never saved or picked from another design gives null. `_sdCompareLive(p)` is the one caller for the tab menu, the command palette and the Publish sheet.
- The part `compare` with `livePageId`: the "On the site" side is the live page's tree (its open tab's baseline, else its last revision through `revisions_list` and `_sdRevisionFetch`), drawn by `compare_render` with the live page's id; "since …" names that revision. The draft's own revisions still feed History's choosers.
- The Publish sheet's row of a new version ("Replaces <page>") carries the compare icon too (`compare` in `_pgPublishRows()`).

## What does it cost?
- Minified: core +322 bytes (+106 gzip), part phone +936 (+227), part compare +645 (+165), part versions +151 (+28). Nothing runs until a window opens.

## How was it checked?
- `tests/designer_tab_states_test.php` (`_pgCompareTarget`), `tests/designer_phone_window_test.php` (`_phInfoKind`, the host), `tests/designer_versions_test.php` (what `start()` resolves, the compare wiring).

Related: docs/memory/designer/designer-new-version-tab.md, docs/memory/designer/designer-phone-preview-window.md, docs/memory/designer/designer-compare-what-changed.md
