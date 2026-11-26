---
name: designer-use-round
description: Plan (owner 2026-11-26): the Page Design Studio's features of 2026-11 used in a browser as a first-time person, their faults fixed, their look brought to the chrome's rules; steps UR1 to UR8
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel message 14119): "görsel sayfa düzenleyicideki yeni özellikler test edilsin ve modernize edilsin."
- The features: `workflow/decisions-made-product-105.md` and the plans beside it, built in 2026-11.

## Why a browser, when the hunts already read them?
- The hunts of these features read the code and wrote failing tests (the `workflow/bug-hunt-areas-*.md` files). A fault of use shows only on the screen: a control nobody finds, a window cut on a phone, a step that needs a reload, a look that left the chrome's rules.
- So each step runs its feature on a sandbox (`workflow/sandbox-and-environment.md`) in a browser. That is the reason Working mode 4 asks for, and the PR names it.

## How does one step work?
- One worker, one feature, one PR. The worker reads the start file, then uses the feature from its first door to its end as a person who has never seen it: at 1440 px and at 390 px, light and dark, once by the keyboard alone, on an empty design and on a design of ten pages.
- It writes down every fault first, then fixes: a failing test first where a pure function is at fault, at most eight fixes, the rest as lines of its memory file.
- The look: each window, panel part and message against `designer/designer-chrome-measures.md`, `designer/designer-chrome-tokens.md` and, for a window, the Design examples screen. What the feature's drawing shows stays; what differs from it without a reason is corrected.
- It keeps `designer/designer-light-and-simple.md` (nothing added to the first load) and `designer/designer-panels-keep.md`.
- A choice of behaviour (a control moved, a step taken out, an accepted label reworded) goes to the architect; the step goes on.
- The PR writes `workflow/designer-use-round-<feature>.md`: what was used and at which sizes, what was found and fixed, what was left and why, what could not be run (an AI action without a key, Figma without a token).

## Which steps? (each S to M)
- UR1 Design variables (`designer/design-variables-overview.md`): the start questions, the Variables tab, the Options chip, "Ask me again", Export and Import.
- UR2 The New design window, its quick starts and the Figma and Canva starts (`designer/new-design-window.md`, `designer/designer-start-import.md`).
- UR3 after SK6 has landed: the Prototype view as a drawing board (`designer/prototype-view.md`, `designer/prototype-sketch-tool.md`, `designer/sketch-page-details.md`, `designer/sketch-build.md`, `designer/sketch-build-again.md`, `designer/prototype-sketch-picture.md`).
- UR4 The Options panel's parts added by the DO steps (`designer/designer-options-overview.md`), in a 240 px column too (`designer/designer-options-narrow.md`).
- UR5 after CO5 has landed: a shared component's fields per place and its variants (`designer/component-overrides-fields.md`, `designer/component-overrides-this-place.md`).
- UR6 "Preview on a phone" (`designer/designer-phone-preview-rules.md`), History's Activity tab (`designer/designer-activity.md`), "Compare with the live page" (`designer/designer-compare-look-tab.md`).
- UR7 My library (`designer/designer-my-library-overview.md`), a page from a document (`designer/document-to-page.md`), canvas guides (`designer/designer-canvas-guides-overview.md`), a picture's focus point (`designer/image-focus-overview.md`).
- UR8 after CU3 has landed: "Clean up"; then the close-out: the round's files read as one, a fault that repeated across features written into `designer/` as a rule, this file deleted.

## Who gives it out, and how many at once?
- The coordinator of the designer plans (`workflow/coordinator-second-paused.md`). At most three steps at a time; two steps whose fixes fall into one file wait on each other, as any two tasks do.

Related: docs/memory/workflow/bug-hunt.md
