---
name: designer-home-tidy
description: Plan (owner 2026-11-26): the New design window and the Page Design Studio home made plain: one icon a start, a grouped rail, start cards only on the first visit; steps HT1 to HT4
priority: 8
---

## What is asked?
- Owner (2026-11-26, agent channel message 14316, with a picture of the New design window): "görseldeki ekranda ikonlar anlaşılmayacak karışık ve arayüzü modern değil. bu alanları da bir elden geçirelim. görsel sayfa tasarımcısı yazılımın kalbi kargaşa azalmalı, anlamlama ve kullanıcı deneyimi artmalı. liste ana sayfası dahil. bunları sen çiz"
- The editor's frame (its bars and panels) is not in this plan.

## What is wrong today? (looked at on dev, 2026-11-26)
- The window fills the screen. A rail row has a small drawing nobody can read, an icon and two or three lines; the pane repeats the row's title and sentence, asks one question and leaves three quarters empty.
- The home's first screen is four large start cards that repeat four of the window's seven starts; the designs begin under them. The large card stacks its facts, twelve page chips wrap, a hint line explains the right click.

## What is drawn?
- `designer/designer-home-tidy-drawing.md`: the sheet `new-design.html` (boards N1 to N5) and `home.html` (H1 to H5). Where this file and a sheet differ, the sheet wins.

## What must it never do?
- Change what a start does: `pg_designer_start_kinds()`, the starts' sequence inside a group, the addresses of `pg_designer_start_go_href()`, which framework card comes chosen, what the template gallery lists.
- Add or remove a menu row, a button of the large card or a state, or change which design `pg_designer_home_featured_id()` picks.
- Break the import panes (`designer/designer-start-import-drawing/`) or the import line on a card (its board B11).
- Use a colour beyond greys and the accent (`designer/designer-home-colours.md`), or write a label without `php tools/tr_add.php`.

## Which steps? (M about 1,000 product lines, S under 400; tests red first in `tests/designer_home_test.php`)
- HT1 (M) The New design window, boards N1 to N4: at most 1120 × 720 px; the rail in three groups, a row as one icon tile, name and one line, the small drawings removed; one icon a start as a pure function; the pane head; "Built on" as two cards with ticks; Template chosen by a press and used by the foot's "Use this template".
- HT2 (M) after HT1 has landed: the home, boards H1 to H4: the head row; the four start cards only when the site has no design, as three starting points; the large card's state lines, facts in one row and one row of page chips; tiles with a state mark and one line; the search in the head; the menus' groups; the hint line removed.
- HT3 (S) after HT2 has landed: narrow, boards N5 and H5: the window as a list then a pane with "All starts"; the home in one column, the other designs as a list.
- HT4 (S) Close-out: `designer/new-design-window.md` and `designer-home-screen.md` corrected, the two readings below moved there, this file deleted.

## Which files?
- `includes/designer_screen.php`, `assets/js/designer_home.js`, `assets/css/designer_home.css`: one screen's files, so one step at a time (`workflow/coordinator-worker-steps.md`). A step waits for the drawing's merge, never for a yes.

## Which readings does the architect fix? (2026-11-26, agent channel request 3174)
- The four start cards leave a home that has a design: "New design" is the one way in. No entry of `workflow/DECISIONS.md` binds them.
- Narrow, the window's strip of starts leaves for a list: five of the seven starts were off its edge.

Related: docs/memory/designer/designer-home-tidy-drawing.md, docs/memory/designer/new-design-window.md, docs/memory/designer/designer-home-screen.md, docs/memory/plans/designer-drawings.md
