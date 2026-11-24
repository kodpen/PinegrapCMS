---
name: designer-light-and-simple
description: Every feature added to the visual designer keeps it simple and quick (owner 2026-11-24): where it may sit, how it loads and runs, what is measured, what a step's PR shows
---

## What did the owner say?
- Owner (2026-11-24, approving ten more designer features): "önceliğimiz arayüzü modern ve basit tutmak özellikler girsin ancak kullanıcıda kasma hissi oluşturmasın."

## Where may a new feature sit?
- No new button that stays in the page bar, the canvas bar or a panel's head. A feature enters as one row of a menu that is there (the canvas bar's View menu, the Preview menu, an element's menu, the Publish sheet, History, the New design window) and as a row of the command palette.
- It opens with a default that needs no choice. Further choices sit one level down, behind "More" or in the window's second part. A setting with one sensible value is not shown.
- A row is named by what it does, in the person's words. A first use shows one line of what the thing is for, with "Got it", and never again.
- What is drawn over the canvas is off until asked for, and goes with Escape or its switch.

## How does it load and run?
- As a designer part loaded on first use (`designer-parts-on-demand.md`); the core gets a stub only, so the designer opens no slower for having the feature.
- While off it holds no listener, timer or observer. Work that follows an edit waits until the hand rests (about 300 ms) and never runs during a drag or while a text is typed.
- A server call that takes longer than a moment shows its progress in its own place and never locks the canvas.

## What is measured?
- With the measures and the fixture page of `designer-performance.md`, before and after the step: opening, the first draw and an edit are no slower than the measure's own spread while the feature is off, and an edit at most a tenth slower while it is on.
- What a pointer drags (a handle, a slider, a mirror) keeps sixty frames a second on the fixture page.
- The timing that needs no browser is run as that file says. A browser run is made only for a step that draws while the pointer moves, and its PR says so.

## Which plans does it bind, and what does a step's PR carry?
- Every designer plan written from 2026-11-24 on, and the steps not yet started of the designer plans before them.
- The PR names the menu row the feature sits in, that it is a part, and the numbers measured. A step that misses them is not ready; a feature that cannot meet them goes to the architect, not into the bars.

Related: docs/memory/designer/designer-parts-on-demand.md, docs/memory/designer/designer-performance.md, docs/memory/designer/designer-canvas-bar.md
