---
name: site-run
description: The run of "Describe it" (SB5): the part siterun makes the plan page by page as draft tabs, header and footer once, the line with Stop, a refusal, "Go on", the sketch parts' hooks
module: output
---

## Where does it start?
- "Make my site" (`site-plan-screen.md`) opens `add_system_style.php?start=describe`: `auto_sketch` and `site_run` (`sdDesign.siteRun`). Once `_sdSketchStart()` has shown the sketch, `_sdSiteRun()` loads the designer part `siterun` (`includes/designer_parts/siterun.php`, `when: designer`, `assets/js/style_designer_siterun.js`) and calls `start(_sdSiteRunHost())`, once per editor.
- `start()` takes `pg_site_plan` from `localStorage` and removes it: a reload does not make the site again or spend the day's drafts twice. No plan, or no sketch page paired with it (by `details.address`, else by place), makes nothing.

## What does a page go through?
- Its "Page details" get the plan's name as title and its purpose as description, when empty. The Prototype view zooms to it.
- Draw: `sketchai.draw()` sends the words of `pure.words()` (the brief's name and what it does, cut first; "Page: name. purpose"; the sections between Header and Footer; at most 2,000 characters) as "Describe the page" does.
- Build: `sketchbuild.auto()` builds the draft tab at once; an unsure box takes the reader's best kind (no list to check). A page already built is kept.
- Write: `sketchai.write()` is "Write the texts" of the page. Then the next page.
- Each hook settles once with how it ended (`{ok}`, `{ok: false, message}`, `{off}`, `{stopped}`); the parts' own lines under each frame stay as when run by hand.

## How are the header and footer made?
- Once: the run's build host gives the builder `site` = the brief's name, the header's pages (menu Main) and `footer` (Main and Footer only); `pg_sketch_builder.js` links `site.footer` in the footer when given. The first page makes the two shared components; every later page gets a new reference to them (`host.ref()`), as `_sdSketchDesignShared()` skips sketch-built pages.
- The theme is not the run's: the answers came with the address (`design-variables-questions.md`).

## What does the line say?
- Fixed at the foot of the screen (`.sd-sr`, `style_designer.css`, under the windows): "Making your site: page 3 of 6", the step ("Drawing the sketch of About", "Building About as a draft", "Writing the texts of About"), a bar, Stop.
- Done: "Your site is made: 6 pages, as drafts." / "Save keeps them; Publish puts them on the site.", "Got it". Nothing is saved by the run, as after "Build the page".
- Stop (or Escape, which stops the drawing or the writing): the drawing drops its answer, the writing keeps what it wrote; "Stopped." with "n pages are made; the others wait as sketches.", "Go on" and "Close".
- A refused or failed request ends the run with its reason (the server's message, e.g. the day's drafts used) and the same kept line, "Try again" and "Close". "Go on" / "Try again" start again on that page where it stopped (`pure.stepOf()`: no box draws, drawn builds, built writes). Pinegrap AI off ends it with "Pinegrap AI is off." and no way on.

## Tests and what was not checked?
- `tests/site_run_test.php`: the pure parts, and in Node a run against stand-in parts (order, header and footer once, details, refusal, Try again, Stop, no second start), the wiring. `tests/sketch_builder_test.php`: the footer's own list.
- Not run against a live Pinegrap AI; the browser run used stand-in answers of `designer/sketch_ai`.

Related: docs/memory/designer/site-plan-screen.md, docs/memory/designer/sketch-ai.md, docs/memory/designer/sketch-build.md
