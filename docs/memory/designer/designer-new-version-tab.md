---
name: designer-new-version-tab
description: A live page's new version in the Page Design Studio (NV2): the door "Save as a new version" / "Open the new version", the draft's tab and its pill, "Discard this version", the part versions
---

## Where is the door?
- "Save as a new version" is a row of a live tab's menu, a command palette row (after Save/Publish) and a foot button of the Publish sheet beside "Save this page as a draft", with the line "The page on the site stays as it is." under it (`data-sdp-version`). Where the page has a version the same three read "Open the new version".
- `_pgVersionDoor(p, entry, opts)` (pure, in the core's tab states section, `tests/designer_tab_states_test.php`) decides it: `start` for a saved page on the site, `open` when the versions block names it `live`, '' for a draft, a new or picked page, a page without content rights, an open experiment, before the upgrade (`sdDesign.versionsReady` = `pg_page_bundles_ready()`) or for who cannot change states.

## What does a press do?
- The part `versions` (`assets/js/style_designer_versions.js`, condition `publishcheck`) runs on first use. Start serialises that one tab (`_pgTabsSerialize(key)`); a tab with no change sends no page and the copy stays as the page on the site is. Then `designer/version_start` (`designer-new-version-server.md`).
- On success the draft is loaded (`page_load`) and added as an unchanged tab right of the live one, selected; the live tab gets the stored page back with `_pgContentLay(p, tree, fields, true)` (`force` lays over unsaved changes: they went to the draft). The new draft is held in the editor's state like a bundled draft until NV3.
- An answer with `existing` (a version already there, or a start that lost a race) opens that draft and leaves the live tab's changes where they are, with a warning toast.

## How does the draft's tab look?
- `_pgTabState()` gets `s.version` (the live page's name): the pill "New version" in the draft pill's place, before a schedule badge, accent ground (`.sd-tab-version-badge`); its tip "New version of <page>: it replaces that page when it is published."
- Its tab menu has "Discard this version": a confirm, then `designer/version_discard`; the tab leaves and the live page's tab is selected.

## How was it checked?
- `tests/designer_tab_states_test.php` (pill, door), `tests/designer_versions_test.php` (the part's rows of the versions block and the hooks). The browser run the plan asks for is in NV2's PR, or listed there as not made.

Related: docs/memory/designer/designer-new-version-server.md, docs/memory/designer/designer-tab-states.md, docs/memory/designer/designer-publish-sheet.md
