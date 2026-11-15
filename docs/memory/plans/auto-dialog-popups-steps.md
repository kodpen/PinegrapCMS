---
name: auto-dialog-popups-steps
description: Steps PU1 to PU10 of plans/auto-dialog-popups.md: census, the dialog script, consent, the columns, the screens, designed content, the four blocks, counts, the recovered carts touch, close-out
---

## Which steps are there?
- PU1 (S, no code) Census. Given 2026-11-15 (agent channel message 5180), `@adnan`. Done: #9788, `plans/auto-dialog-popups-census.md`. One memory file:
  - every place that reads or writes `auto_dialogs`: the three screens, the shared form, the API routes, the page's filter in `get_page_content.php`, the tests, the starter rows in both backups;
  - every template or stylesheet that styles `ui-dialog` or the `auto_dialog` classes;
  - who calls `software.open_dialog()`;
  - how the cookie notice sits above the page, so a pop-up leaves it answerable;
  - how PHP can draw a shared component outside a page's tree.
- PU2 (M) after PU1: the `<dialog>` script, the pick in the browser, `open_dialog()` as a wrapper, the session key gone. No schema. Before and after pictures of an old row on a site: the architect's look.
- PU3 (S) after PU2: consent. The one cookie, `sessionStorage` without consent, the catalogue and the revoke names; with the notice on nothing opens until it is answered (the `pg:consent` event). No schema.
- PU4 (M) after PU2: the columns `open_on`, `open_scroll`, `audience` and `place`, the scroll and leave triggers, the form's fields and the API's fields (`api/api-design-dialogs-regions.md`).
- PU5 (M) after PU4: the three screens (`view_auto_dialogs.php`, `add_auto_dialog.php`, `edit_auto_dialog.php`) in the panel's new look. Pictures to the owner.
- PU6 (M) after PU5: `content` and `component_id`. The edit screen asks "What does it show?": an address or a designed pop-up, with links into the designer. A site with no visual design keeps the address kind.
- PU7 (M) after PU6: the four ready blocks. Pictures to the owner.
- PU8 (M) after PU4: the day rollup table, the beacon, the figures on the list and edit screens. Follows `visitors/README.md`.
- PU9 (S) after PU8 and CR1 of `plans/cart-recovery.md`: a take-up by a visitor with a cart calls that plan's writer (channel `page`, source `popup`).
- PU10 (S) close-out: the topic files in `platform/` and `designer/`, this plan and its steps file deleted.

## Which steps run in turn?
- PU4, PU5 and PU6 share the screens and the form: one at a time. PU3 and PU8 may run beside them.
