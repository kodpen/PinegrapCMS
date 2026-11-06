---
name: template-previews
description: Plan (owner 2026-11-05): template and design pictures drawn live from the real pages, a short installer design step with a home-page preview and a page tour, the installer at Bootstrap 6 width
---

## What is this plan?
- The owner's two complaints of 2026-11-05: the installer's Site Design step is far too long (22 cards of seven points each: 11,349 px tall at 1280 px wide, 21,434 px on a phone), and the Visual Page Editor home shows the templates and the site's own design as drawings (`pg_design_thumb_svg()`), not as they look.
- After it: a grid of small live pictures of each template's home page, the chosen template's home page large above it, every page and e-mail in a tour in a new tab; on the editor home and in Choose a Template the templates' real home pages, and the site's design as its home page really looks.
- Screens: `template-previews-visuals.md`. Today's code: `template-previews-code.md`. Steps: `template-previews-steps.md` (TP1-TP3), `template-previews-steps-2.md` (TP4-TP5).

## Which decisions bind it? (owner, 2026-11-05)
1. A picture is the real page, not a stored image: a 1280×800 frame of the page scaled into its box, script-free (`sandbox=""`), not focusable, loaded when it comes into view. A screenshot needs a browser on the server, which shared hosting does not have; stored thumbnails come only if the frames prove too heavy, as a later step.
2. Installer: the step previews the home page only; all pages and e-mails are seen in the tour that "Tüm sayfaları yeni sekmede gör" opens.
3. A template is shown, and opens, in the look and palette its file names. Picking a template in the installer sets both pickers to them (still changeable). Choose a Template gets a first choice "Şablonun teması" / "Şablonun paleti", checked by default; its "Bu temayı kullan" button goes; choosing another look or palette redraws every card in it.
4. The site's design on the editor home is its page as a first-time visitor sees it: no consent banner, chat bubble, staff bar, and no visit counted.
5. The installer is as wide as Bootstrap 6's widest container, 1440 px (its own rule capped it at 1280); from 1280 px the design step takes the side column's room.
6. The drawings stay as the fallback: a template whose home page cannot be drawn, a design with no page to open.

## What stays as it is?
- The install flow, its fields and the two-request template install (`platform/design-template-install-at-setup.md`); Standard installation; the editor home's cards, menu and status; Use This Template and its `?look=&palette=`.
- No schema change, no new setting.

## Where does it start?
- TP1 is done (#7258), TP2 (#7409). TP2 and TP3 run side by side after it (different files); TP4 after TP3; TP5 closes.
- Read first: `designer/designer-home-screen.md`, `platform/design-template-install-at-setup.md`, `platform/background-render.md`.
