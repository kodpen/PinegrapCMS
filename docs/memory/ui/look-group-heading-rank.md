---
name: look-group-heading-rank
description: Look A: a group heading inside a card is .pg-group-label and never outranks the card's title (architect, 2026-11-17, looks of S3 #10314 and S4 #10316)
---

## Which class does a heading inside a card take?
- The card's own title is `.pg-title-section`. A heading that groups fields or rows inside the card is `.pg-group-label`, whatever the old markup used (`h3.h5`, `h5`, `h6`, a bold `div`).
- Architect (2026-11-17, agent channel, looks of S3 #10314 and S4 #10316): a group heading never reads larger or heavier than its card's title. Seen on Edit Login Region ("Not Logged In" and "Logged In" inside Options) and Add Ad Region ("Region Behavior" inside Options), both `h3.h5` in `includes/forms/`.

## How is it checked in a look?
- On the 1440 picture the largest text inside each card is the card's title. A step's worker checks the other forms of its group for the same heading and changes them in the same push.

Related: docs/memory/ui/look-type-and-recipes.md, docs/memory/ui/look-field-widths.md
