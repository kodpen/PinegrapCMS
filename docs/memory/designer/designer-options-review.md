---
name: designer-options-review
description: Options panel "Only what I changed" (DP9, #10699): the switch at the bottom with the count, the list of changed rows of all three tabs, "Reset all" as one undo step
---

## What is the switch?
- "Only what I changed" ("Yalnız değiştirdiklerim"; under 300 px "What I changed") stands under the Options panel, always at its bottom (`#sd-props-review`, 38 px, a 32 by 18 switch, `role="switch"`). Its count is the three tabs' badges added; no badge at 0.
- Shown for the single element's panel and for several elements; hidden for no selection, a read-only content-level panel, a text's inner element and cells to merge.
- It stays on from one element to the next while the editor is open (not kept in the browser). Typing in "Search or add an option…" or a jump to a row (`_sdOptReveal()`) turns it off.

## What does it show when on?
- The tab strip and the tabs' panes are hidden; one list (`#sd-review-list`) stands in their place: per changed row its label, its path "tab › section" (the outermost section card's title), its value and a reset arrow. Content, Style, Advanced, each in the panel's order.
- The value as the closed card's summary reads it (`_sdChgRowOf()`): a colour with its square, "— Multiple —" when several elements differ, "none" when empty. Several elements: the row once, with in how many it is changed ("2/3").
- The list holds exactly the rows the badges count: the dots of all three tabs, Translation's card and rows outside a card left out (`_sdSectHeadsFill()`). Nothing changed: "No option is changed here."

## How does it work?
- On, `renderProperties()` and `renderMultiProperties()` build every tab and every section's body as the search does, keep them hidden, and `_sdReviewAfter()` asks the part to draw the list from the drawn rows (`_sdReviewRows()`).
- A reset arrow clicks the row's own dot button (`.sd-chg-reset`), so one element and several reset as the dot does, one undo step each. Disabled where the dot's is, and in a locked panel (K4).
- "Reset all" asks first (`pgConfirm`, "Reset all changed options?"), then `_sdReviewResetAll()` resets every row inside one `_sdUndoAsOne()`: one undo step. A row is found again after each redraw by tab, card and its place in the card; a row its reset leaves changed is tried once. A toast says how many went back.
- "Copy style" under the list is D27 (DP10): not drawn before it works, as the identity row's brush.

## Where is the code?
- Core hooks: the block `"Only what I changed": the core's hooks` in `style_designer.src.js` (`_sdReviewFoot`, `_sdReviewAfter`, `_sdReviewSet`, `_sdReviewRows`, `_sdReviewResetAll`, `_sdReviewHost`). `_sdMcMark()` puts `data-mc-n` / `data-mc-of` on a several-element row's label.
- Part `review` (`designer-parts-on-demand.md`): `pg_options_review.js` (window.PGOptionsReview: `list()`, `total()`, `resetAll()`, pure), then `style_designer_review.js` (the list's markup and clicks). Loaded the first time the switch is turned on.

## Where is it tested?
- `tests/designer_options_review_test.php`: the list equal to the dots of all three tabs (with the core's `_sdChgSummary()` / `_sdChgTotal()`), the order and paths, values of each kind and the share, "Reset all" as one undo step (`_sdUndoAsOne()` with `_sdStateRunTakes()`), the part's registration and hooks.
- Not tested: the drawing, the focus and the question in a browser.
