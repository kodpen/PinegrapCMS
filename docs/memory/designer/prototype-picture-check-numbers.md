---
name: prototype-picture-check-numbers
description: The prototype canvas page picture check's measurements (PV7): both ways on a 41-page design, how and where each number was taken
---

## Where and how was it measured?
- A cloud container: 4 vCPU Intel Xeon 2.10 GHz, 16 GB, headless Chromium (Playwright's, software drawing), PHP's built-in server and MariaDB on the same machine (`tools/setup_sandbox.sh`).
- The Clinic template installed: 41 pages in three tab groups, four shared parts (header, footer, account bar, band), system widgets on 40 pages, two links made broken.
- A throwaway canvas over the editor: 248-wide frames, the picture cut at 1280×1600, eight a row, Fit 0.37; IntersectionObserver, 6 loading at once. Times from `performance.now()` to the frame's `load`; memory from CDP `Performance.getMetrics` after a forced GC and the RSS of all Chromium processes; frames from 3 s of `requestAnimationFrame` panning or zooming. Three runs each; ranges below.

## How fast did the pictures come?
- Fit, all 41 in view: way 1 (Preview document) first 115-326 ms, last 1.74-2.02 s; way 2 (saved address) first 507-594 ms, last 3.06-3.18 s.
- 100 %, 18 in view: way 1 first 221-284 ms, last 0.98-1.11 s; way 2 first 551-631 ms, last 1.53-1.56 s.
- Markers: all 1,017 on the 41 pictures (shared parts once per page) read in 46-65 ms, at most 17 ms a picture.
- Way 1's parts: the document 18 ms a tab (31 at most, 0.7 s for 41); `preview_widgets` 23 ms a tab (55 at most, 0.97 s for 40 tabs, one request each); a document 18 KB (54 KB at most).

## How much memory?
- After 41 pictures: way 1 +15.4 MB JS heap, +190-206 MB RSS, +26,007 nodes; way 2 +27.2 MB, +283-292 MB, +45,864 nodes (its frames run in processes of their own).
- 10, 20 and 41 pictures: way 1 +80, +125, +206 MB RSS, about 4 MB a picture; way 2 +110, +179, +283 MB, about 5.6 MB.

## How smooth were pan and zoom?
- At Fit with nothing loading: no pictures 16.7 ms median, 0 frames over 20 ms; way 1 with the stilling rule 16.7 ms, p95 16.8, 0; way 1 without it 33.3 ms, p95 50, 74 of 101 frames over; way 2 16.7 ms, 0-1 over.
- At 100 % while pictures load: way 1 p95 16.8 ms panning and 33.3 zooming, 3-17 frames over; way 2 p95 33-50 and 67-83 ms, 11-19 over.

## What did widgets, fonts and an unsaved tab look like?
- Way 1 draws widgets signed out, as the Preview does; way 2 as the signed-in operator, with the site's edit toolbar, and the e-mail cloak's no-script text.
- The design uses the system font stack and Bootstrap Icons from jsDelivr: both ways drew them. A font the site serves without CORS was not tried; way 2 would lose it (`design-live-pictures.md`).
- An unsaved heading on the open tab: way 1 shows it, way 2 the saved text. Pictures (1440, light and dark) are under agent channel message 5717.
- The Clinic template has few images; images under the cut load too (no `loading="lazy"` without scripts), so PV8 measures memory and the last picture once on an image-heavy store template and writes them here.

## What did PV8 measure on an image-heavy design (#9896)?
- The Store template (37 pages, 159 images in the pictures) on the same container, all 37 pictures alive: +136 MB RSS of all Chromium processes, 3.7 MB a picture; the Clinic design 3.8 MB. Under 8 MB, so the cap stays at 48. The last picture came about 4 s after the view opened (one run, polled every 0.5 s).
