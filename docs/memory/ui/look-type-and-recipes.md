---
name: look-type-and-recipes
description: Look A's typeface (self-hosted Inter 4.1), the type scale and tone tokens, and the recipe classes in backend.src.css (#8879): which class draws which part, the block's place, the unlayered body family
---

## Where do the type and the recipes live?
- In their own block at the end of `assets/css/backend.src.css`: "Look A: the panel's type and recipes". The page widths (DL1b) get a block of their own. A recipe is written there once; a screen's own class only places things.
- The numbers are the sheet's (`plans/panel-design-language-look.md`, `sheet.css`); where Bootstrap 6 gives the same pixel the token aliases it (`--pg-text-base` is `--bs-font-size-sm`, `--pg-text-meta` is `--bs-font-size-xs`).

## Where does the font come from?
- Inter 4.1, `assets/fonts/inter/InterVariable.woff2` and `-Italic.woff2`, the release's `web/` files unmodified, with `LICENSE.txt` (SIL OFL 1.1). `tests/look_recipes_test.php` pins their sha256; an update changes the files, licence and digests together.
- One variable family "Inter"; headings get the Display cut with `font-variation-settings: "opsz" 32` (`--pg-opsz-display`). Body features `cv11`, `ss01` as in the sheet.
- `--bs-body-font-family` is set unlayered at the file's end: Bootstrap 6 declares it on `:root` outside any layer, so a rule in `@layer custom` loses. Only the family changed on existing screens; sizes move per screen family (DL5).

## Which tokens did the look add?
- Type: `--pg-text-count` 11, `-meta` 12, `-2` 13, `-base` 14, `-status-title` 15, `-section-title` 17, `-screen-title` 24 px; `--pg-weight-regular` 400, `-medium` 500, `-strong` 550, `-title` 650; `--pg-leading` 1.45.
- Surfaces: `--pg-surface-1`/`-2` (card gradient), `-hi`, `-inset`, `-float`, `--pg-hover`, `--pg-quiet`, `--pg-shadow-card`, `--pg-shadow-float`, `--pg-radius-card` 16, `--pg-radius-sheet` 20.
- Accent of the look: `--pg-accent`, `--pg-accent-1`/`-2` (gradient), `--pg-accent-glow`, `--pg-on-accent-fill`. Fields: `--pg-field-bg`, `-line`, `-shadow`; `--pg-thumb`, `--pg-thumb-shadow`.
- Colours flip with `light-dark()` (colours only); the tint strengths (`--pg-tint-from`, `-to`, `-line`, `-select`) and shadows sit in the light and dark theme blocks.

## Which tones are there?
- `--pg-tone-blue`, `-violet`, `-amber`, `-teal`, `-rose`, `-gray`; by module `team` blue, `shop` teal, `content` rose, `accounting` and `warning` amber, `reports` violet, `system` gray. A class `.pg-tone-<name>` sets `--pg-tone`, which the pill and the tile read (gray without one). A new module gets its line in the token block, never a colour in a screen.

## Which class draws which part?
- Type: `.pg-title-screen`, `.pg-title-section`, `.pg-title-status`, `.pg-text-2`, `.pg-text-meta`, `.pg-tabular`.
- Card `.pg-card` (`.pg-card-sheet` 20 px corners), `.pg-card-sub`; inner card `.pg-card-inner` with `.pg-card-inner-row`; floating bar `.pg-floatbar`.
- Field: `.pg-field-label` (+ `.pg-field-label-note`), `.pg-input` on an input, select or textarea, or on a wrapper holding a bare input and `.pg-input-end`; `.pg-input-sm` in a row; error `.is-invalid` or `aria-invalid="true"`, message `.pg-field-help.pg-field-error`. `.pg-switch`, `.pg-checkbox` on a native checkbox.
- Buttons: `.pg-button` + `-fill` (one per view), `-quiet`, `-ghost`, `-danger`, `-sm`; `.pg-icon-button` (+ `-sm`, `.is-active` or `aria-pressed`).
- Segmented control `.pg-seg` > `.pg-seg-item` (chosen: `.is-active`, `aria-pressed`, `aria-selected`, `aria-current`), `.pg-seg-count`.
- `.pg-pill` (status), `.pg-icon-tile` (+ `-lg` for a settings row), `.pg-initials` (+ `-2`, `-3`), `.pg-count`, `.pg-unread-dot`.
- Row `.pg-list-row` (`.is-read` dims it): `-main`, `-head`, `-title`, `-title-verb`, `-time`, `-text` (two lines), `-actions`, `-place`, `-side`, `-quick` (shown on hover in place of the time). Gathered rows: `.pg-gather` > `.pg-gather-line` (`-end`), `.pg-gather-toggle`.
- Names avoid `.pg-tile`, `.pg-avatar`, `.pg-row`, `.pg-float`, `.pg-btn-*` (taken).

Related: docs/memory/ui/panel-css-tokens.md
