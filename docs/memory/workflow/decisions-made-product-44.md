---
name: decisions-made-product-44
description: Decisions list, product part 44 (2026-11-11 on): a muted, looping hero video may play by itself, with a pause button; a colour mode (light, dark, follow the system) for a whole design
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## May a hero video play by itself? (owner, 2026-11-11)
- Owner (2026-11-11): yes, on the terms put to him ("Evet, bu koşullarla"). A muted, looping video in a hero, a page's opening section, may play by itself; it has a pause button, and it does not play when the visitor prefers reduced motion.
- Only that video: the question was put as an exception to "no block plays by itself". Until then no block played a video, the marquee bands were the only motion that starts by itself, and no carousel autoplays (`designer/designer-block-motion.md`); all of that stays.
- Why: the owner ordered bolder pages with motion the same day (the template line, recorded in workflow's design-template-owner-line file), and such a page opens on a moving picture. The pause button and the stop under reduced motion keep WCAG 2.2.2, the reason behind the motion rules.
- The other answer, not taken: "Hayır, oynamasın" (no video plays by itself).
- Not built yet: step B9 of `plans/designer-bold-pages-steps-2.md`.

## Can a whole design be light, dark or follow the system? (owner, 2026-11-11)
- Owner (2026-11-11): yes ("Evet, eklensin"). The Theme pane gets a colour mode for the whole design: light, dark or follow the system.
- The terms put to him with the question:
  - A visitor's own theme switcher still wins over the design's mode.
  - The default is today's behaviour, so a running site does not change until its designer picks a mode.
  - The mode is kept in a new `style` column beside the look and the palette, added by a migration step: `decisions-made-schema-15.md`.
- Why: without it a design that is dark from top to bottom (a showroom, a product page) is made by hand, band by band; today only a visitor's theme switcher turns a whole page dark (`designer/design-theme-look-and-palette.md`).
- The other answer, not taken: "Hayır, gerek yok" (no mode).
- Not built yet: step B11 of `plans/designer-bold-pages-steps-2.md`.
