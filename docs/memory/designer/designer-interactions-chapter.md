---
name: designer-interactions-chapter
description: The chapter interaction (Colour chapter): the page takes a section's background and text colour while it holds the window's middle; fields, script, reduced motion, canvas, panel, tests.
module: designer_fx
---

## What is Colour chapter?
- `_fx` effect `chapter` (PR #8855): while the section holds the window's middle, the page's `<body>` takes the section's background and text colour in a transition; once no chapter holds the middle the body takes its own back. Turns the hard cut between a light and a dark band into a change of the whole page.
- One field, `duration` (100–5000 ms, default 600, the shared `pg_fx_numbers()` range): the length of the colour change. No trigger, so the head style never hides the section; trigger, delay, easing, start and repeat drop out. `pg_fx_attrs()` writes the duration only when it is not 600.

## How does pg_fx.src.js play it?
- At bind it reads the section's computed colours once (`pgFx.chapterColours()`): a transparent background gives none, and a text colour equal to the body's computed one gives none, so the page keeps its own there.
- Each scroll frame (with parallax, `float()`) `pgFx.chapterAt()` finds the last chapter whose box spans the window's middle; at the page's end a chapter whose top is in the window's lower half counts too (a short last band). `paint()` writes `background-color` and `color` inline on the body with a `background-color …ms ease,color …ms ease` transition, the coming chapter's duration (the leaving one's when the page goes back).
- The body's own inline colours and transition are kept the first time it is painted and put back when every chapter is gone.
- Reduced motion: the colours still change, with `transition: none` (a cut). Without the script the page keeps its own colours.

## Why not on the editor's canvas?
- The canvas rewrites its body's style attribute on every render, and an operator editing a band should see the page's own colours. Under `pgFxEditing` a chapter is not bound; the Preview and the published page play it.

## Where does the operator set it?
- Interactions section (`style_designer_interactions.js`): Effect > Colour chapter, offered on sections (`semantic`) and containers, kept in the list on any node that has it; one Duration row and a note. No Play button.

## How is it tested?
- `tests/designer_fx_test.php`: `test_designer_fx_chapter` (normalizer, attributes), the twin cases, `chapterAt` and `chapterColours` under Node, the panel, and `test_designer_fx_script_chapters` (paint on scroll, own colours back, cut under reduced motion, nothing on the canvas).

Related: docs/memory/designer/designer-interactions.md, docs/memory/designer/designer-block-theme-colours.md
