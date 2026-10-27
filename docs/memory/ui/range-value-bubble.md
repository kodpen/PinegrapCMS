---
name: range-value-bubble
description: Panel sliders as the Bootstrap 6 Range with a value bubble (step B9e, #2207): markup, pgUI.range.bubble(), data-pg-range, where it is used, the site Range that stays v5
---

## What does a panel slider look like?
- A `.form-range` wrapper around `<input type="range" class="form-range-input">`, plus `.pg-range-bubble` (1.75rem of room above for the bubble, which sits over the thumb and would cover the row above). The wrapper carries the tokens; `backend.src.css` sets thumb and fill to `--pg-primary`. The input keeps `name`, `id`, `min`, `max`, `step`: it is still what the form posts.
- No separate value label next to the slider: the bubble shows the value.

## How is it started?
- Bootstrap starts every `.form-range` only at DOMContentLoaded and without a formatter. `pgUI.range.bubble(wrapper, format)` (`pg_ui.js`) disposes that instance and makes one with `bubble: true` and `format(value)` as the bubble text; it waits for `pgUI.ready()` and returns a promise (null under Bootstrap 5).
- It also disposes the instances of wrappers no longer in the page: Bootstrap keeps each instance in a Map until `dispose()`, and the editor inspector is redrawn on every selection.
- Server markup: `data-pg-range="<unit>"` on the wrapper (`"%"`); `pgBindRanges(scope)` in `backend.src.js` binds them on page load and from `pgInitInjected()` for settings cards. Markup built in JS calls `pgUI.range.bubble()` itself.
- A value set from code needs `instance.update()` (none of today's places does this).

## Where is it used?
- Editor inspector (`style_designer.js`): progress bar Value, gradient Mid on the linear and radial tabs (`_sdPercent` formats); label editor Scroll X (`LabelEditor` in `backend.src.js`, plain number).
- Settings: Features, image Quality When Resizing (40-100, the save clamps the same); General, Alerts, Free disk space below % (0-50).

## What stays a v5 range?
- The palette's Range form field (`style_designer.js`, `def.type === 'range'` gets `form-range` on the input): it is site output on 5.3.8.
