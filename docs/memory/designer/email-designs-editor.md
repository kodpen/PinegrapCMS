---
name: email-designs-editor
description: How the visual editor shows an e-mail design (MA5c #9465): the 600 px canvas and Phone view, the mail palette group, what a mail cannot carry hidden, the note on style sheets
---

## How does the designer show it?
- `_sdEmailCanvasSync()` (called after every `renderCanvas()` in `render()`) sets `.sd-canvas-email` on `#sd-canvas`: the page widths hide and two buttons show, "E-mail" (600 px, chosen on open) and "Phone view" (375 px). A page that is no e-mail goes back to full width.
- The two width buttons are icons with their name as title and aria-label: with words they pushed the bar's last button off at 1440 px (channel 2941).
- `body.sd-email-design` hides what a mail cannot carry: Animations, Prototype and the JavaScript section of the right panel; the Design pane's note says the style sheets show on the canvas only, since the mail pass takes out every `<link>`, `<style>` and `<script>`. The Interactions panel is still shown (MA5c2).
- The palette's E-mail group (family file `assets/js/style_designer_blocks_mail.js`, prefix `mail-`) shows only in an e-mail design, first; elsewhere it is left out (`_sdBlockPaletteGroups()`).

Related: docs/memory/designer/email-designs.md
