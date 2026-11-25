---
name: designer-tips-switch
description: The Help window's "Tips" switch hides every first-use line in the designer, the older ones with their own browser key too: the shared check K._sdTipsOff() (#11711)
---

## Does "Tips" off hide the older first-use lines too?
- Yes (#11711). The older lines keep their own drawing and browser key, but each reads the switch before it shows. Before #11711 they ignored it and still showed with "Tips" off.
- Bands and side by side ask the tour model (`PGDesignerTour.tipDue()`, which reads `tipsOff`).
- Select similar, overrides, states, variables, Sketch reading and building, Preview as call the core's `K._sdTipsOff()` (`_sdTipsOff()` / pure `_sdTipsOffIn(raw)` in `style_designer.src.js`) in their own seen check: off counts as seen, so the Sketch reading starts at once without its ask. Their own key is left as it is, so on again shows only what was not seen.
- A new first-use line asks through `_sdTipAsk()`; one with its own key reads `K._sdTipsOff()`. Tested in `tests/designer_tips_off_test.php`.
