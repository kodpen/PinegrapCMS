---
name: canvas-consents
description: A live page's writes with consent (11.0.193, #9180): add and change through the bridge, the first-write question per page version, "Last written through <canvas>" on a row
module: guarded_frame
---

## What may a page write?
- Through the bridge (`canvas-bridge.md`) two calls: `add` {table, cells} and `change` {table, row, revision, cells}; cells an object of column ids. Only a table its canvas binds for writing (`canvas-tables.md`): any other answers `not_bound`, a read binding `forbidden`.
- Every write is the table's own (`ws_wtable_row_create()`, `ws_wtable_row_update()`): its rights (no write right answers `forbidden`), its checks (`invalid` with `field` and `cells`, the words of `ws_wtable_screen_error_text()`), its events and bin; a row changed since answers `changed_meanwhile` with the `revision` now. No delete: the question says "cannot delete".
- The answer is the row as read (`ws_wtable_row_present()`).

## When is the reader asked?
- Before the first write in their name: `ws_canvas_consent_question()` reads the answer kept for the page's newest version (`ws_canvas_page_version()`, MAX `version_no` of its html versions). No answer, or a yes given before a write table was bound (pure `ws_canvas_consent_state()` compares the kept table ids): `ask`. A new page version has no answer, so it asks again.
- The page gets only `consent`; `ws_page_call` takes `ask` off the answer and hands it to the card. The card shows a `<dialog>` over the page ("Sales board writes in your name?", each reachable bound table with "Adds and changes rows, cannot delete" / "Only reads" and a Writes/Reads pill, the hint, Don't allow, Allow); on a phone a sheet from the bottom with both buttons full width. Escape counts as Don't allow.
- The answer goes to `ws_page_consent` {message_id, answer yes|no, version}; a version that is no longer the newest is refused ("The page has a new version: open it again."). Allow sends the waiting request again; writes asked meanwhile wait for the same dialog.
- Said no: every write answers `consent`, the card shows the amber band "Write permission not given. The page only reads." with Change, which asks again.

## What is stored?
- `ws_canvas_consents` (owner answer #8925): `message_id`, `user_id`, `page_version`, `answer` yes|no, `tables` (JSON ids of the write tables asked about), `created_at`; unique per canvas, person and version; answering again replaces it. A thread's purge removes its rows.
- `ws_wtable_rows.via_message_id` and the same in `ws_wtable_row_bin` (owner answer #8926): the canvas a row was last written through, 0 for any other write (a change on the table screen, the API, a flow, a restore sets 0). The bin keeps it with the row. `ws_wtable_row_via_given()` reads it from a write's input; the API row carries `via_message_id`.

## Where does "through <canvas>" show?
- Rows have no history; the table screen's row form (`workspace_wtables.php`) shows "Last written through <canvas>" (`ws_canvas_via_label()`), or "…a canvas you do not reach" when the reader does not read its channel.

## How is it proven?
- `tests/workspace_canvas_consents_test.php`: the state, the write ids, the call shapes, the refusals, the via field, the card's text. Not run in a browser or on a database in this step.
