---
name: designer-options-section-cards
description: The Options panel's identity row and section cards (D3, D6–D9, D11): own section first and open, general sections closed with a count and summary, closed bodies not drawn, one source for changed rows
---

## What stands at the top of the Options panel?
- The identity row (`_sdIdentityRow()`): the element's Overview icon in a 36 px box, its name (cut with an ellipsis), the tag it draws on the canvas (`_sdIdTag()`, never cut), the last three steps of its path (a click selects that parent; under 300 px only the last after "… ›"), and a three-dot menu: Duplicate, Delete, Show in Overview (`showCtxMenu()`, guarded as the canvas menu is). The root has no menu.
- The brush (copy style) of the drawing is D27, a later step: it is not drawn before it works.

## How are sections drawn?
- `sect()` and `as()` both draw a card through `_sdSectMake()` / `_sdSectCard()`: chevron, name, a count badge (absent at 0), and a summary shown while closed.
- In the single element's panel (`renderProperties()` sets `_sdSectBuild`) the first card of the element's own options markup is its own section (`_sdSectTakeOwn()`): put first, open unless this browser closed it. Every other card is general and closed until this browser opened it. Kept per browser in `sd_cs_<sid>` as before (`_sdSectOpenRead()`). Outside that panel (several elements, a text's inner element) a section opens as before.

## Is a closed card's body built?
- No. Its markup waits in `_sdPanelLazy[sid]`; a click on the head stores it open and draws the panel again, keeping the scroll and the head's focus.
- The search and a jump to a control (`focusClassControl()`, `_sdOpenWidgetSection()` through `_sdSectOpenFor()`) draw the bodies they need first.
- Measured on a 80-element page: selecting 97 → 44 ms median, 2,649 → 122 nodes in the panel; one option change 68 → 42 ms.

## What counts as changed?
- One source: `markModifiedProps(root)` puts the dot class on a changed row's label; a closed card is marked on a detached copy of its body (`_sdSectExpand()`), so its count is the dots it would show. The comparison is `_sdChgDiffers()`; the head's count and summary `_sdChgSummary()` (first changed row: a colour square with its name, else its value, "none" when empty, "default" with no changed row); a tab's count `_sdChgTotal()`.
- The pure helpers sit in one marked block of `style_designer.src.js`, run under Node by `tests/designer_options_changed_test.php`.

## When does the scroll stay?
- `_sdPanelKind()`: another kind of element opens the panel at the top; one of the same kind keeps the scroll. Undo and redo keep the selection, the scroll (`_sdStepReselect()`) and the right column's tab.
