---
name: preview-as-check
description: "Check all cases" of the Preview as built (PW4): where it starts, which cases it draws, how each is measured in the Preview's own window, the list, a row opening its case, the core hooks
module: designer
---

## Where does it start?
- The last row of the "Case" menu and of the narrow window's sheet (part `previewas`, `checkRowHtml()`), and the command palette row "Check all cases" marked "Preview" (`act_check_cases`, listed when the page holds a system widget, `_sdPvAsHasWidget()`). No bar button.
- From the palette with the Preview closed, `_sdPvAsCheck()` opens it and sets `_sdPvAs.check`; the run starts once the first answer has reached the part. A page with one case has no row and runs nothing.
- Asked while a draw of the bar is on its way (`S.drawing`), the run waits (`S.waiting`) and starts with that draw's answer or once an opened row is drawn; a failed draw starts none. Before the Preview as hunt the late answer replaced a case's document half way and the wrong page was measured.

## Which cases are drawn?
- `checkRuns(cases, audience)` (pure, in the part): each case of the page for signed out; where the page has an audience condition also for signed in and for signed out returning (the server takes one visitor). Runs in case order per visitor. The chosen records stay as kept.
- Each case is asked with `K._sdPvAsAsk(choice)` (`preview_widgets` with that choice, `_sdPvAsApply(payload, given)`), never kept; while a run draws, `_sdPvAs.running` makes the live preview wait (`_sdLive.again`).

## How is a case measured?
- Its document is shown in the Preview's own window (`K._sdPvAsShow()`), each widget's markup between two comments `pg-pva-w:<id>` / `/pg-pva-w`. The part waits for `readyState` complete, the fonts and a frame (at most 8 s), then `measure()` reads it at the window's width.
- A widget draws nothing when the range between its comments has no box of 2px. A picture is missing when shown with no address or loaded with no natural width (`pictureMissing()`); a lazy or still loading one is not judged.
- A text leaves its box (`textLeaves()`): the innermost non-inline block holding text is wider than its box, runs past the window's right edge while starting inside it, or is cut at the bottom of a box with a held height. A scroller, an ellipsis and a slide beyond the window are not findings.
- At most five findings of a kind per case. The bar, the list and the outline (`#pg-pva`, `#pg-pva-hl`) are never measured.

## What is the list?
- `listHtml()`: 300px over the page's right edge (`.pg-pva-ck`), a sheet of at most half the window when narrow. Head and foot from `checkHead()`; a row per case with a finding (up to three named, then "+n more") or "Could not be drawn" with "Try again". "Stop" keeps what was found; "Check again" runs it anew.
- Nothing found: no list, the teal line "Every case looks right. n cases drawn.". The list is held in the part only and goes with the window.

## What does a row do?
- `openResult()`: the case becomes the page's kept choice (quiet), the part draws it itself the way the run did, then outlines each place in amber over the page (`#pg-pva-hl` on `<html>`, as wide as the text runs) and scrolls to the first. The page is never changed.
- A place is found again by its element path from the body (`pathOf()`, `nth-child` only, so the widget comments do not count). When it lies under the list the list moves to the left edge. The outline goes with the next click in the page.

## How is it tested?
- `tests/preview_as_check_test.php`: the pure functions run in Node, the core hooks as source. `measure()` needs a browser; it was tried once in headless Chromium on a sample page, not in CI.
