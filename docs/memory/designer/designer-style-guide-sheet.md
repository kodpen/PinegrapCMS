---
name: designer-style-guide-sheet
description: The Style guide sheet of the Preview menu (SG2, 2026.11.0): the part 'styleguide', the window, its frame with the canvas's stylesheets, the blocks, Light and Dark, the server's style_guide answer
module: design_style_guide
---

## Where is it opened?
- "Style guide" is the last row of the Preview menu (`#sd-vb-styleguide`, under a line) and a row of the command palette; designers only (the row and the part are `designer`, the server's `style_guide` is not in the content roles' list).
- The core keeps a stub, `_sdStyleGuideOpen()`, and a host (`_sdStyleGuideHost()`); the part `styleguide` (`assets/js/style_designer_styleguide.js`) loads on the first press.
