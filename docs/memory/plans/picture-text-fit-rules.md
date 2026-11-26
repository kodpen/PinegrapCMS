---
name: picture-text-fit-rules
description: Plan picture-text-fit, the fit's rules (architect 2026-11-26): a text's room from the picture's edges and the other layers, the order grow, wrap, shrink, what stays of the old rule
---

## What is a text's room? (architect, 2026-11-26, agent channel request 3129)
- The rectangle a translated text may use, found in the text layer's own turned frame. It starts as the source box and goes out on each side to the first thing in its way.
- The picture's edge, less a margin: the source box's own distance to that edge when the text leans on it (nearer than a tenth of the picture's shorter side), else 4% of the shorter side.
- A visible layer whose box stands in the way, less a gap: the smaller of the source's distance to it and half the text's size. Toward another text layer the room ends at the middle between the two source boxes, less half that gap, since the other text grows too.
- A layer under the text that holds the whole source box is not in the way; when it is smaller than the picture (a card, a ribbon) its box bounds the room, so a text on a card stays on the card.
- A hidden layer, the picture layer of a photo and a layer above the text that covers it whole are not read.
- The numbers are the architect's; PF1's measure may change them, and the change is written here.

## In which order is a text fitted?
- 1. Grow: the translation at the source's size on the source's lines. The aligned side stays while the room on the other side is enough (`textFit()`); when it is not, the box slides inside the room toward the free side and the lines keep their alignment. Upright the box's middle stays, and slides the same way.
- 2. Wrap: when the widest line is wider than the room, each of the text's own lines is broken between words into the fewest lines that fit the room's width, balanced in length, as long as the whole fits the room's height. A word is never broken.
- 3. Shrink: the size goes down to the floor, with the wrap that needs the least shrink (`M.languageFloor()`, unchanged). Under the floor the text is drawn at the floor in the room's place and the language is marked "Look by hand", as today.

## What stays of the old rule?
- A text that fits its source's box is drawn exactly as today: same place, same size.
- No text ends smaller than the old rule made it: where the room cannot be read (a text turned so that its frame cuts every neighbour, a document with no size) the old rule's result is taken.
- A sticker's label is fitted inside its sticker, as before; its room is the label box.
- Right-to-left languages are still not drawn.

## What does the draw get?
- The fit answers, per changed text layer, the box, the size and the lines; `M.languageDoc()` and its PHP twin write them into the language's document, so the stage, the save's renderer and the server's draw paint the same numbers and need no rule of their own.
- The server measures with its own letter widths (`core/picture-text-auto-server.md`); a fixture is kept inside both measures by a tolerance the test names.
