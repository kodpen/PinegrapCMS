---
name: designer-first-tour-model
description: The first tour's pure model (window.PGDesignerTour, FT1): stops and their controls per access, Next and Back skipping a missing control, what was seen kept per person in the browser
---

## Where is the model?
- `assets/js/pg_designer_tour.js`, `window.PGDesignerTour`: pure, no element, no storage. Tested in Node by `tests/designer_tour_model_test.php`.
- It is the first source of the part `tour` (`includes/designer_parts/tour.php`), which draws the card and the tour (`designer-first-tour-card.md`).

## Which stops, on which controls?
- `stops(access)`: `full` (any access but `content`): add `#sd-components` (the palette), shape `#sd-properties` else `#sd-canvas`, see `#sd-preview-menu-btn`, publish `#sd-ajax-save` else `#sd-publish-page`, find `#sd-status-shortcuts` (the "?" button; the command palette is Ctrl+Shift+P and has no button of its own).
- `content` (`_sdAccess()` of a content editor): change `#sd-canvas`, edit `#sd-properties` else `#sd-canvas`, send `#sd-ajax-save` else `#sd-publish-page`.
- The model holds ids, not sentences: the part maps each id to its `_sdT()` sentence, so `check_lang.php` sees them in a `style_designer_<part>.js`.
- The test fails an anchor the editor's screen (`includes/designer_screen.php`, `style_designer.src.js`) no longer draws as `id="…"`.

## How is a stop whose control is not on the screen skipped?
- The caller passes `has(selector)` (is it on the screen now). A stop uses its first selector found; with none it is skipped.
- `first(list, has)`, then `move(list, at, dir, has)` at each Next (1) and Back (-1), asked again at every step so a control gone since the tour began is skipped too. Next past the last: `null`, the tour is over. Back before the first stays on the current stop.

## Where is what was seen kept?
- In the browser, per person, as the panel keeps its lists' choices (`commerce/orders-list-figure-strip.md`): localStorage `key(userId)` = `pg.designer.<user id>.tour` (`sdDesign.userId`). No table or column (the plan says so).
- `read(raw)` / `write(state)`: `{ v: 1, offered, seen: { full, content }, tips: { <name>: true }, tipsOff }`; anything unreadable is a first visit; only `true` values and tip names of `[a-z0-9_-]{1,40}` are read.
- `offer(state, access, busy)`: the card is shown when never offered, that access's tour not seen, and `busy` has no `dialog`, `typing` or `dragging`. `offered(state)` after the card was shown (once, whatever the answer); `seen(state, access)` after the tour ended by its last stop, Skip or Escape.
- First-use tips (FT3): `tipDue(state, name)`, `tipSeen(state, name)` ("Got it"), `tipsOn(state, on)` (the "Tips" switch; seen tips stay seen).
- Every function returns a new state; the one given is not changed.
