---
name: prototype-suggest
description: The Prototype view's Suggest targets (PV11b): how a link without a target is matched to a page, the dialog's rows and keys, the dashed proposed arrows, what Apply writes
---

## How is a target suggested?
- `PGProtoHotspots.suggest(link, pages)` (pure, `assets/js/pg_proto_hotspots.js`): label, page name and page title folded by `foldWords()`: lower case, Turkish letters plain (İ and ı to i, the marks of ç ğ ö ş ü dropped), punctuation to spaces, the words of `SUGGEST_FILLER` dropped (go, to, the, page, view, see, git, sayfa, gör, incele).
- Score per page: 3 when its name or title equals the label, 2 when every word of it begins a word of the label (a word of three letters or more as a beginning, so "Sepete git" finds Sepet), 1 to 1.5 when only some do.
- Sure only when exactly one page matches at all and it scores 2 or more; else unsure, at most three candidates, best first. A label that matches nothing is tried with the nearest heading above it (`headingOf(tree, nodeId)`: a heading content or h1 to h6 before it in its top-level block, read by the view from the tab's tree); what that finds is always unsure.
- The link's own page and a page with `bin` are never offered.

## What does the dialog show?
- "Suggest targets" with its count, the Prototype bar's own quiet button (first of its tools), while the design has links without a target; it opens a panel dialog (`.sd-proto-suggest`, `data-bs-modal="false"`, no backdrop) at the canvas's right, so the map stays in sight.
- `suggestRows()`: one row per drawable link without a target, grouped by frame, a shared part's once. The head counts "n sure · n to ask · n without a suggestion".
- A sure row is ticked with its page selected; an unsure row asks "Where should "label" go?" with its candidates and Skip (a candidate selects and ticks); a row without a suggestion sits on "Choose a page", its tick disabled. A label's click picks the link on the canvas (`_sdProtoPick()`).
- Recipes: Apply `pg-button-fill` ("n hedefi uygula", key `Apply {var} targets`), Cancel and Skip `pg-button-ghost`, a candidate `pg-button-quiet pg-button-sm` (chosen: `aria-pressed` and a check), tick `pg-checkbox`, selector `pg-input pg-input-sm`.
- Architect (2026-11-17, 8712, 8747): no `btn btn-` line; the bar button is quiet (amber is the warning); a long label takes its line, the selector under it across the row, a long page name ellipsised and whole in its title; the tick at the label's first line.
- Keys: Tab through the rows, Space ticks, Enter on a candidate picks it, Escape cancels. The dialog's keys never reach the designer's shortcuts.

## How are the proposed arrows drawn?
- The graph canvas core's `dashed(list)` draws a set of `{ id, from, to, port }` dashed (`pgc-edge-dashed`, dash `6 4`), replacing the set before; `[]` clears it. The view passes the ticked rows of the selected frame (`_sdProtoSuggestArrows()`), again on each tick, selector change, frame selection and redraw.

## What does Apply write?
- Nothing is written before "Apply n"; Cancel, Escape and closing the view write nothing, and a skipped row is suggested again next time.
- `suggestBatch(picks)` groups the ticked rows by the tab they are written on (a shared part's on a tab placing it, `_sdProtoFrameTab()`); a row on a locked tab, on no tab or with no page stays with its reason.
- The shared component and live page warnings are asked once for the batch (`pgConfirm`), naming the parts and pages. Each tab is opened in turn, its lock answered by a heartbeat, then its rows written with one `saveState()`: undo on that tab takes back its batch.
- A row whose link changed, or that the operator may not edit, stays with its reason; then the toast "n links connected" and the links are read again.

## How is it checked?
- `tests/proto_hotspots_test.php`: `_suggest_targets`, `_suggest_rows`, `_suggest_batch`, `_view_suggest_targets`; `tests/graph_canvas_test.php`: `_dashed_edges`.
