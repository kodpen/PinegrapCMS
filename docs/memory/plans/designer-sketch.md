---
name: designer-sketch
description: Plan (owner 2026-11-24, before the launch): the Prototype view as a drawing board: a page sketched as boxes is read, built from blocks and filled with the variables; SK1 to SK9
priority: 7
---

## What is asked?
- Owner (2026-11-24): "prototip hala çok zayıf ... tasarım yapma dışındaki herşey prototip adımında çözülsün, hatta prototip bir mimar çizim alanı gibi bile olabilir, kullanıcı prototipte istediği site sayfasını çizer, sistem onun çiziminden nasıl bir düzen istediğini anlar, header, intro vs. vs. otomatik seçer".
- His choice: rules read the drawing, Pinegrap AI helps where it is on (`workflow/decisions-made-product-105.md`).

## What is built?
- "Sketch" in the canvas bar's Prototype mode (`designer/prototype-view.md`, the graph canvas core, no second engine): "New page" puts an empty frame; inside it boxes are drawn by dragging, moved, resized, with snapping and guides. Undo, keys and zoom as the view has them.
- Each box carries a chip with what it was read as: Header, Intro, Features, Cards, Text, Picture, Gallery, Form, Call to action, Footer, or a system widget (catalog, blog list, booking). The chip's menu changes it; a typed word in a box is its heading.
- Reading by rules, pure and tested: place and size in the frame, equal boxes in a row, the order from the top; a cross is a picture, lines are text, a small box is a button. An unsure box is asked, never guessed in silence.
- "Build the page": each section becomes a real tree from the starter blocks and palette components, wearing the design's variables (`designer-variables.md`), with sample content; the header and footer become the design's shared components. The sketch is kept with the page's draft, so "Build again" after a change keeps the sections already edited.
- "Page details" on a frame, so that only design is left for the editor: name, address, page type and its widget, place in the menu, title and description, who may see it. Links are the view's arrows as today.
- With Pinegrap AI on: "Describe the page" draws the sketch from words, "Write the texts" fills the sections; nothing is sent without that switch.
- A reference picture can lie under a frame, dimmed, to draw over; with AI on, "Read the picture" proposes the boxes.
- Storage: with the page's draft; a step that finds it needs a table or a column stops and asks the architect.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- SK1 (M) The reader, pure: boxes in, sections with kinds and unsure marks out. Done (#11493, `designer/sketch-reader.md`).
- SK2 (M) The Sketch tool on the graph canvas: frame, boxes, chips, snapping. Done #11497 (designer/prototype-sketch-tool.md; box kinds `header` … `booking` in `SD_SKETCH_KINDS`, `kind: ''` left to the reader; kept per browser until SK4).
- SK3 (M) after SK1: the builder, pure: a section list to a tree from starters, with variables. Done #11532 (`designer/sketch-builder.md`; header and footer handed back for SK4 to make shared components, widgets placed by the caller, sample words given by the caller).
- SK4 (M) after SK2, SK3: "Build the page", "Build again", the sketch kept with the draft. Done #11642 (`designer/sketch-build.md`).
- SK5 (M) after SK2: "Page details". Done #11545 (`designer/sketch-page-details.md`; the More menu on a sketch page, values kept with the sketch).
- SK6 (S) after SK4: the two AI actions. Done #11682 (`designer/sketch-ai.md`).
- SK7 (S) after SK2: the reference picture and "Read the picture". Picture done #11557 (designer/prototype-sketch-picture.md); "Read the picture" done #11584 (designer/sketch-read-picture.md).
- SK8 (M) after SK4: the feel round: 60 frames a second on a hundred boxes, touch and pen, every key, light and dark; measured, the numbers in the PR.
- SK9 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- `designer/prototype-view-rules.md`; the look from the Design examples screen.
- Claim as `[plan:designer-sketch] <step> — <topic>`.
