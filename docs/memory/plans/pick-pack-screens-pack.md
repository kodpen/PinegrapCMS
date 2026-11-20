---
name: pick-pack-screens-pack
description: Plan pick-pack, how packing is put together (PP4, PP5): the Pack screen's two states, a refused scan, the checked pack, the short pick's drawer, the Packing card
---

## Which screens, and by which rules?
- PP4: Orders › Pack. PP5: the short pick, the Packing card. The shelf and wave screens and the words: `pick-pack-screens.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets. A user with the packing right alone sees no price, total or e-mail here.

## How is the Pack screen laid out? (PP4)
- One purpose a screen, as the barcode stock screens (`ui/barcode-scanner-detection.md`). First state: one large field "Scan the order's code" kept in focus, a camera button beside it, under it "or pick an address" as a search select.
- Second state, an address open: head with the order number, ship-by day and "7 of 9 units". The lines as cards [cards]: picture, name, variant options in bold, units as "2 / 3"; a full line turns green and sinks. The scan field stays in focus above them; a typed code works the same.
- A refused scan: the field's row turns red for two seconds with the reason in one sentence ("This is size M; the order wants L") and the sound; nothing else moves.
- All units scanned: the screen turns to "Packed" with what happened as rows (label made and opened for printing, marketplace package accepted) and "Next order" as the fill, in focus.
- Foot while packing: "Short…" quiet, "Leave this order" quiet. No fill until the pack is checked.
- A phone gets the same order: field, lines, foot; the camera opens over the lines.

## How is a short pick shown? (PP5)
- "Short…" opens a drawer: the lines not full, each with the missing units and a switch "Set this location's count to what was found" with its number field; one line says the address waits. Foot: "Close as short" fill, Cancel quiet.
- The order screen and the wave show the "Short" pill with the missing lines; "Finish the pack" reopens the Pack screen on it.

## How is the Packing card laid out? (PP5)
- A dashboard card: four figures [cards: figure] (waiting, oldest, due today or late, packed today), under them a row a packer for this week: packs, units an hour, refused scans. Only for the orders right, not for the packing right alone.

Related: docs/memory/plans/pick-pack-screens.md, docs/memory/plans/pick-pack-steps.md
