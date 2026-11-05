---
name: readme-screenshots
description: The README's screenshots in .github/readme/: which files, light and dark, why not docs/, how they are taken again before a release
---

## Where are the README's screenshots?
- `.github/readme/*.webp`, shown by `README.md` and `README.tr.md` (a hero image under the introduction, a "Screenshots" section after "What's inside").
- `docs/` never reaches `main` (`project-overview.md`) and GitHub's front page shows `main`'s README, so the images live under `.github/`, which a release keeps.
- The editor is one image, the dark panel. The dashboard, Workspace and orders, and the phone dashboard and Workspace, have a light and a dark file, picked by `<picture>` with `prefers-color-scheme`. The store and its products are light only: a site page, not the panel.
- WebP at quality 86: desktop 1600 px wide (the editor 2000 px), phones 640 px; about 1 MB in all. Keep them small: every retake adds its bytes to the history.

## How are they taken again?
- From a sandbox of `development` (`tools/setup_sandbox.sh`) filled with the owner's demo and Workspace seeds, panel in English: interface tours marked seen, the Online Store template opened in the editor and published, the new dashboard on with three goals, the security settings of a configured site and one backup (System Status in the green; SSL stays red on http).
- Playwright at 1440x900, the editor at 1728x1080 so its canvas reaches the 1200 px desktop layout, phones as an iPhone 15 Pro, all at 2x; site pages signed out, so no admin toolbar shows.
- The scripts and their notes are kept with the owner's seeds outside the repository (no Node step in the tree).
- A retake keeps the file names, so the READMEs do not change. The final pass is taken from the interface of the version being released.
