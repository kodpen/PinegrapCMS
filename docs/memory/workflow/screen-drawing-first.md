---
name: screen-drawing-first
description: Working rule (2026-11-20): a step that adds or lays out again a settings pane, a form drawer or dialog, a tab or a new kind of screen starts only from a drawing the owner said yes to
---

## What did the owner say?
- Owner (2026-11-20, in the architect's window, with a picture of a mailbox's drawer in Site Settings › Mailboxes): "yeni tasarlanan sayfaların ayarların çok karışık anlaşılmaz ve modernizasyon kurallarımız dikkate alınmadan yapılmış olması ... yarın bir ajana tekrar tarif edip bunların hepsini baştan tasarlatınca 2x token harcamış olacağız", and "her yeni oturumda sözlerim sürekli atlanıyor".
- What he met: the drawer as one long form with paragraphs of help, no way to remove the mailbox, and a Google account connected on Connections that Mailboxes showed as not connected (#10825).
- What was found: look A binds a screen's parts (`ui/look-new-parts.md`), nothing bound how a whole screen is put together. None of the thirteen plans written from 2026-11-18 to 2026-11-20 held a drawing, but the two he had asked to see first (`decisions-made-panel-12.md`).

## What is the rule? (architect's working rule, 2026-11-20, agent channel message 10947)
- A step starts only when its plan names a drawing the owner said yes to, if it adds or lays out again, in the panel or the Workspace: a settings pane or part, a drawer or dialog that holds a form, a tab on a screen, or a screen of a new kind (a board, a wizard, a scan screen).
- No drawing is needed for a list screen on the list table driver, an add or edit screen on the edit layout (`ui/edit-screen-wide-layout.md`), or one control added to a screen that stays as it is. These are copied from the Design examples screen and keep `ui/screen-composition-rules.md`.
- A coordinator that meets such a step with no drawing does not start it and writes to the architect. Steps with no screen go on; a worker already building finishes.

## Who draws, and how is it approved?
- The plan's author draws with the plan: the research agent for its plans, the architect for a screen the owner complained of. One HTML page a plan under `plans/<plan>/`, from look A's recipes, light and dark, a phone width where the screen is used on one. The model: `plans/app-access-redraw/app-edit.html`.
- The architect asks the owner with the picture (`owner-question-picture.md`), "Evet, böyle çıksın" first. The yes goes into the panel decisions with the drawing's path, and the plan's step names it.
- A drawing is the layout agreed: labels and help lines are the step's, each through `lang()`.

## What does the step's PR carry?
- The drawing it followed and pictures of the built screen in light and dark (`ui/panel-screenshots.md`); where it left the drawing, a line says why.
- A pattern the step builds for the first time (a folded settings row, a drawer's foot) joins the Design examples screen in the same PR (`ui/look-design-examples.md`, `plans/design-examples-patterns.md`).

Related: docs/memory/ui/screen-composition-rules.md, docs/memory/ui/look-new-parts.md, docs/memory/workflow/decisions-made-panel-12.md
