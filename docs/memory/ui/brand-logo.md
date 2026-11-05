---
name: brand-logo
description: The Pinegrap logo (the squirrel's space window, the single-line wordmark): its files, which shape goes where, the panel's moving loop, the still frames, private label
---

## Which files are the logo?
- `assets/images/`: `pinegrap-mascot.svg` (the square window), `pinegrap-wordmark.svg` (the wordmark, master stroke 18), `pinegrap-logo.svg` (both, the full logo), all still, no script.
- The panel's loop: `pinegrap-mascot-loop.webm` (VP9), `pinegrap-mascot-loop.mp4` (H.264), 128 px, 12 s, every motion's period divides 12 s so it loops without a seam; `pinegrap-mascot-still.webp` is its poster.
- Rasters of the square: `logo.png` (256 px, the default `LOGO_URL`), `icon-192.png`, `icon-512.png` and `icon-maskable-512.png` (full bleed, the drawing inside the safe circle) for `manifest.php`.
- The drawing is a trace of the old mascot; the source pages and the loop's frames were made outside the repo.

## Which shape goes where?
- Owner, 2026-11-04 (`workflow/decisions-made-product-28.md`): a narrow place shows the square mascot, a wide place the full logo, whatever the place showed before.
- The panel itself (header, loading curtain, installer, theme designer) moves; icons, files and the live site's image edit button (`add_edit_button_for_images()`, `frontend.src.js` and its hand-edited `.min.js` twin) stay still.

## How is the panel's logo drawn?
- `pg_logo_markup($id_suffix)` (`includes/fn/output.php`): a `<video>` muted, looping, `playsinline`, `preload="none"`, poster the still frame, WebM then MP4, then the wordmark `pg_logo_svg($id_suffix)` and the name as `.visually-hidden` text. The video is `aria-hidden`.
- `pgLogoVideos()` at the end of `backend.src.js` starts every `video[data-pg-logo-video]` unless reduced motion, a data saver or `deviceMemory` under 2; nothing is fetched before `play()`, and a refused play keeps the poster.
- `pg_logo_svg()` draws the wordmark at stroke 22 (the header's size) with stops `var(--pg-logo-color-1, #3D5AFE)`, `-3` (purple) and `-2` (pink); dark mode lightens them in `backend.src.css`. Its view box keeps 100 units above the letters so a flex row centres them on the mascot. Every copy on one page needs its own suffix: Firefox draws no gradient defined inside a hidden copy (header '', curtain '-pl', installer '-in1'...'-in5', theme designer '-td').
- Sizes: `--pg-logo-h` (the mascot's side, 32px default) on a wrapper; the corners are CSS (`border-radius: 22.6%`).
- Nothing sits over the logo (owner, 2026-11-04): the short version that was a pill under the mascot is gone; it stays in the page title (`Pinegrap 26.11 | …`).

## What does the header show on a phone?
- Below 768px the mascot alone; below 390px nothing, so the controls keep the row (`ui/panel-top-bar-phone.md`).

## What does private label change?
- With `PRIVATE_LABEL` the header and the theme designer show `LOGO_URL` and the curtain the dots alone, as before.

## What was removed?
- The old lockup's path list (an icon and "Pinegrap™"), the very old "PineGrap™" app icons and `icon_image_editor.png` (the previous image editor's logo), which `clean_up.php` deletes from installed sites.
- Tests: `tests/brand_logo_test.php`, `tests/panel_header_phone_test.php`.
