---
name: designer-first-tour
description: Plan (owner 2026-11-24, level 1): the designer's first tour of five stops, "How do I" pointers on the real controls, one-line first-use tips and an empty canvas that offers starts; steps FT1 to FT4
priority: 7
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a person who opens the designer for the first time is not left alone with eight panels. It is also the designer's part of the ease-of-use study before the launch.

## What is there today?
- The designer opens with every panel and no word of introduction; its help is the list of keyboard shortcuts. An empty page shows an empty canvas.

## What is built?
- The first time, a small card in a lower corner: "New here? A one-minute tour", "Start", "Not now". It is offered once and never opens by itself.
- The tour has five stops on the real controls, each a ring and one sentence with Next, Back and Skip: add something (the palette), shape it (the canvas and Options), see it (Preview), put it on the site (Publish), find anything (the command palette).
- "How do I…" beside the keyboard shortcuts, under the "?" button: about ten tasks (add a section, change the colours, make it fit a phone, show a list of records, publish), each three pointers on the real controls. "Take the tour" is its first row.
- First-use tips: the first open of a feature shows one line under its head with "Got it". "Tips" under the "?" button turns them all off.
- An empty page's canvas offers three starts in its middle: add a section, sketch it, from a document; each shown only when its feature is there.
- A content editor gets three stops of their own: what they may change, how, and how it reaches the site.

## What must it keep?
- Never over a dialog, never while a text is typed or something is dragged, never twice. Escape ends it. The first card is announced and takes no focus; from "Start" focus goes to each stop's bubble and back where it was; every stop is read by a screen reader.
- What was seen is kept per browser; FT1 looks whether the panel already keeps such a thing per person and uses it. No table or column.
- Every sentence is a translated text, short enough for a phone's width. The part loads only when a tour or a tip is due.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- FT1 (S) The model, pure: stops and their anchors per role, the order, what was seen, a stop whose control is not on the screen skipped. Done (#11531, `designer/designer-first-tour-model.md`).
- FT2 (M) after FT1 and DD14: the card and the tour. Done (#11646, `designer/designer-first-tour-card.md`). Boards T1 to T3 and T7 of `designer/designer-first-tour-drawing.md`; where a line here differs, the drawing wins. The PR body carries a table of the boards: what was built for each, what differs and why.
- FT3 (M) after FT2: "How do I…", first-use tips, the empty canvas's starts. Boards T4 to T6 of `designer/designer-first-tour-help-drawing.md`, and T7 for the phone; where a line here differs, the drawing wins. The PR body carries a table of the boards: what was built for each, what differs and why.
- FT4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- The drawing: `designer/designer-first-tour-drawing.md` and `designer-first-tour-help-drawing.md`; it decides where the two differ (`workflow/screen-follows-drawing.md`). Claim as `[plan:designer-first-tour] <step> — <topic>`.
