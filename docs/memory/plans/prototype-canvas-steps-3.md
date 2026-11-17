---
name: prototype-canvas-steps-3
description: Plan prototype-canvas, steps PV7 to PV10 and PV12: the page picture's technical check, the picture in the frame, marks on it, drawing from a mark, close-out
---

## Which steps are there?
- PV7 (S, no product code) The picture's technical check. At once. Done in #9864: `designer/prototype-picture-check.md`.
  - Asked: can a frame show the page as F1 says for forty pages, and can a link's rectangle be read from it?
  - First way: a frame without `allow-scripts` whose document is what the Preview builds for that tab's tree (`designer/designer-live-preview.md`, `generateHTML()` with a tree), 1280 wide, scaled; made when in view (the core's `thumb()`), at most six loading at once. It shows unsaved work.
  - Second way: `PGLivePicture` on a saved page's address (`designer/design-live-pictures.md`): the saved state only, and its frame cannot be read.
  - Measured on a design of forty pages: the time to the first and to the last picture in view, memory, pan and zoom at Fit, what system widgets and fonts look like, what an unsaved tab shows.
  - Answered in a new `designer/prototype-picture-check.md`: the way to take; the sandbox it needs and why that is safe (no script runs, nothing is sent, what the frame can reach); how a link's element is found (a marker written only for the picture, never saved or published); the numbers; how many pictures may live at once.
  - The architect confirms the way in the agent channel before PV8 starts.
- PV8 (M) The picture in the frame. After PV7 and PV2. Done in #9896.
  - By the confirmed way (F1, F3) a frame is its head and the picture (`FRAME` gains the picture's width, its cut height and the head); the sketch is the loading state and the fallback. Link rows stay until PV9.
  - No script ever runs in a picture; it takes no pointer and no focus (`aria-hidden`); made when in view, dropped beyond the cap of PV7.
  - "Read the links again" makes the pictures of changed tabs again. Reduced motion: no fade.
  - Tests for the pure part (sizes, the cut, the foot's count). Features: F1, F2, F3.
- PV9 (M) Marks on the picture. After PV8, PV5 and PV6. Done in #10040: `designer/prototype-marks.md`.
  - Rectangles (F5) read from the picture by PV7's marker, scaled to the frame. A link under the cut, in a closed menu or hidden counts in the foot (F3). Link rows leave the frame; the list carries them.
  - `portAt()` answers the mark's point and side, so arrows start there (F14); F13's amber mark sits on the shared frame's picture.
  - A shared frame gets its picture here; PV8 left it the sketch (architect, 2026-11-16, agent channel message 6079). The part alone, at its own height: at least today's sketch strip, at most the page's cut. Arrows of links under a page's cut leave from the foot on the fade.
  - Tests: a rectangle to frame units, the side chosen, the foot's rule. Features: F3, F5, F13, F14.
- PV10 (M) Drawing from a mark. After PV9. Done in #10101: `designer/prototype-marks.md`.
  - A press on a mark (F21, F22, F23) starts the core's connect with the port's name; a drawn arrow's head can be dragged; the keyboard path.
  - Tests on `hrefPatch()` and pictures of both warnings show K1 to K4. Its sheet goes to the owner.
- The steps from the owner's answer (PV11a, PV11b) are in `plans/prototype-canvas-steps-4.md`; they come after PV10.
- PV12 (S) Close-out: `designer/prototype-view.md`, `prototype-hotspots.md`, `prototype-thumbnails.md` and `prototype-places.md` rewritten as what is now; the core's files in `ui/`; every F and K number checked against the code with its PR; this plan, its files and its folder deleted.
