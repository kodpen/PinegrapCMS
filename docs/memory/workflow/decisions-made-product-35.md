---
name: decisions-made-product-35
description: Decisions list, product part 35 (2026-11-05 on): templates and designs shown as their real pages (live pictures), the installer's short design step and tour, its 1440 px width
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## How are templates and designs pictured? (#7258, #7409, #7412, #7419)
- Owner (2026-11-05): a picture is the real page, not a stored image: a 1280×800 frame of the page scaled into its box, script-free (`sandbox=""`), not focusable, loaded when it comes into view (`designer/design-live-pictures.md`). Reason: a screenshot needs a browser on the server, which shared hosting does not have. Stored thumbnails come only if the frames prove too heavy on a real site (the editor home slow to settle), as a new plan.
- The drawings (`pg_design_thumb_svg()`) stay as the fallback: a template whose home page cannot be drawn, a design with no page to open.
- The site's design on the editor home is its page as a first-time visitor sees it: no consent banner, chat bubble or staff bar, no visit counted (`designer/design-preview-address.md`).
- A template is shown, and opens, in the look and palette its file names. Choose a Template's picker starts on "Template's theme" / "Template's palette"; its card button "Use this theme" went; another choice redraws every card (`designer/designer-home-pictures.md`).

## What does the installer's design step show? (#7409)
- Owner (2026-11-05): the step previews the home page only; every page and e-mail is seen in the tour that "See all pages in a new tab" opens. Picking a template sets both pickers to its theme, still changeable. Reason: 22 cards of seven points made the step 11,349 px tall at 1280 px (21,434 px on a phone).
- The installer is as wide as Bootstrap 6's widest container, 1440 px; from 1280 px the design step takes the side column's room (`platform/install-design-step.md`).
