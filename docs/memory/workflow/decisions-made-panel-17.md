---
name: decisions-made-panel-17
description: Decisions list, panel part 17 (2026-11-22 on): main menu row icons redrawn on category-coloured tiles, the menu's structure stays, System tools lists every tool in one panel
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-panel-16.md`.

## Are the main menu's row icons redrawn? (owner, 2026-11-22, agent channel)
- Owner (message 12070): "şu ana menüdeki hareketli çok renkli ikonlar ve anlamları tam kafama oturmadı. örneğin eposta truncu, otomasyon akış mor vs. gibi renkler ve ikon boyut ve uyumları hoşuma gitmiyor."
- Shown two rounds. On the first (message 12098): "mevcut ikonlardan klasör sayfa ve ürün ikonları dışında hepsi değişebilir." On the second (`ui/main-menu-icons-drawing/icons.html` and the moving sample `menu-motion-dark.gif`), message 12156: "bu şekilde ayarlayalım."
- Binds: every row's icon is the one of `icons.js` in that folder: one line family, one size, on a tile whose colour says the row's category (the untitled block blue, Content teal, Design rose, Ecommerce amber, Accounting lime, Marketing violet, System grey). One small move on hover, half a second.
- Pages, File Manager and Products keep today's idea; every other row takes the drawing's. Calendars shows today's day; Automation flows moves while a flow runs.
- The ERP row stays "Resource planning (ERP)" and gets a ledger, not a calculator. Communication keeps his choice of 2026-11-14, the speech bubble with one arrow out and one in (`decisions-made-panel-8.md`). System tools keeps the lid that opens.
- It replaces, in the entry of 2026-11-02 (`decisions-made-panel-2.md`), the rows' drawings in two colours with an accent per icon. What that entry asked for, a menu easy to read, the tile keeps. A panel entry's icon on its tile stays, in the row's category colour.
- Why: the colours and sizes did not fit each other, and several icons did not say what their row is.

## Does the menu's structure change? (owner, 2026-11-22, message 12156)
- No. Owner: "menünün drawer gibi kopuk olacak sanılabilir şu anki yapısı kalmalı."
- Binds: a row with entries opens its panel inside the menu, never a detached drawer, and ends in an arrow on every width; a row that goes straight to its screen (Dashboard, Automation flows, Incoming e-mail) has none. Why: without the arrow a person cannot tell the two kinds of row apart on a phone.

## What does the System tools row open? (owner, 2026-11-22, agent channel poll 220, message 12163)
- Owner (message 12156): "sistem araçları içinde eksikler var ek sayfa yerine system_tools.php içindekiler sistem araçları menü öğe altında olmalı."
- Asked with the drawing `ui/main-menu-icons-drawing/tools.html` (today's two levels beside one list). His answer: "Hepsi tek listede, ayrı sayfa kalsın".
- Binds: the row's panel lists every tool of the System tools screen directly, the groups' names as headings; no second level and no entry for the screen itself. The screen stays, opened from Site Settings' "System tools" row and the search; the entry of 2026-10-30 (`decisions-made-product-14.md`) is unchanged.
- Each tool keeps its gate, its target and its confirmation; a group with no tool for the person prints no heading.

## How is it built? (architect, 2026-11-22)
- `plans/main-menu-icons-redraw.md`, steps MI1 to MI3, one worker at a time.
