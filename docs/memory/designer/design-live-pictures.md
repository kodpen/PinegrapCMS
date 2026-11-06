---
name: design-live-pictures
description: Live pictures of templates and designs (the real page in a scaled, script-free frame), the preview address of a template page or tour, the tour page; what the browser side does
module: design_previews
---

## What is a live picture?
- The real page, not a stored image: a 1280×800 frame scaled into a 16:10 box (`.pg-live-picture`, backend.src.css). A screenshot would need a browser on the server, which shared hosting lacks (template-previews plan, owner 2026-11-05).
- PHP prints only the box: `pg_design_live_picture($src, $opts)` (`includes/fn/design_previews.php`) with `data-src`, an optional `label` (the box gets `role="img"`), `class`, `fallback` HTML (trusted, e.g. `pg_design_thumb_svg()`), `width`/`height`. No address: `.pg-failed` and the fallback at once.

## What does the browser side do?
- `assets/js/pg_live_picture.js`, bundle `pg_live_picture.min.js` (`window.PGLivePicture`); the screen prints `pg_js_bundle_tags('assets/js/pg_live_picture.min.js')`. On load it scans `[data-pg-live-picture]`.
- The frame is made when the box comes into view (IntersectionObserver, 200 px ahead): `sandbox=""` (no script), `tabindex="-1"`, `aria-hidden`, `pointer-events: none` (the box's parent takes the click). ResizeObserver keeps the scale.
- `.pg-loading` shows a quiet shimmer (none with reduced motion); `.pg-loaded` fades the frame in. No `load` in 12 s: the frame goes, `.pg-failed`, the fallback shows (or a hatched box).
- A 404 page still fires `load` and the sandboxed frame cannot be read, so only a timeout falls back: an address that cannot be drawn should be avoided by the caller (e.g. pages from `pg_design_template_preview_pages()`).
- `PGLivePicture.set(el, src)` swaps a box to another page (another look or palette); `scan(scope)` for markup added later; `fit(el)`.
- The sandboxed page has an opaque origin: fonts served from the site without CORS would not load; the templates' icons come from jsDelivr (`pg_design_icons_url()`), which sends CORS.

## How is a preview address built?
- `pg_design_preview_address('install'|'panel', $args)`: installer `?install_action=design_preview|design_tour&template=&look=&palette=&local=&page=`, panel `PATH/SOFTWARE_DIRECTORY/design_preview.php?template=&look=&palette=&tour=1&page=` (`design-preview-address.md`). Look and palette only when `pg_design_looks()`/`pg_design_palettes()` have them; template and page keys checked by pattern, a bad template gives ''. `page => '{page}'` stays literal for `pg_design_template_preview_html()`'s `$link`.

## What is the tour?
- `pg_design_tour_html($opts)`: a whole standalone document (own inline CSS and script, light/dark from the system, `noindex`): bar with the template's icon and name, "Template preview · look · palette · sample content", a page `<select>` with an E-mails `<optgroup>`, previous/next, "n / total", desktop/phone, the continue button; under it the page full size in a frame (`sandbox="allow-scripts"`, so its own links and Bootstrap scripts work).
- The frame's page posts `{pgDesignPreview: key}`; the tour takes it only from its frame's window and updates the bar.
- Options: `where`, `template`, `page` (start), `look`/`palette` (absent: the template's own), `local`, `continue_href` (a link, `target="_top"`) or none: a button that tries `window.close()` and then says "You can close this tab.", `continue_label`. '' for an unknown template or one with nothing to show.
- The installer's `design_tour` action (TP2) and `design_preview.php?tour=1` (TP3) print it.
