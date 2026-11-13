---
name: panel-pill-contrast
description: Panel pills and badges at 4.5:1 in both themes: the badge weight floor against fw-lighter/fw-light, subtle-fill badge inks mixed 80% toward the text colour, .pg-pill text 70%, the sweep and its test
---

## Why did the Gift Cards amounts look faint?
- Not their colours: the Balance (`.badge.theme-secondary`) and Original Amt (`.badge.theme-primary`) pills compute to 6.6:1 and 7.7:1 in both themes, and the code badge 5.1–10.8:1.
- Their `fw-lighter` set the 12px text at weight 100. Hairline strokes blend into the fill, so the visible ink read near 2:1 (owner screenshot, 2026-11-13).

## How is the weight kept?
- `.badge:is(.fw-lighter, .fw-light)` takes `var(--pg-weight-regular)` (400). It is unlayered (after `.software-navbar.fixed-top` in `backend.src.css`): the weight utilities sit in a layer above `@layer custom`.
- It covers the 3 `fw-lighter` and about 54 `fw-light` badges (sessions, users, orders, comments, commissions, sign-in…) without touching their markup.
- A new badge needs no weight class; never set one below regular.

## How does a badge on a subtle fill read?
- `.badge-subtle` and `.bg-subtle-<tone>` + `.fg-emphasis-<tone>` take the tone's ink mixed 80% with `--pg-fg-emphasis`, set as `--bs-<tone>-fg` and `--bs-<tone>-fg-emphasis` on `.badge` only. Bootstrap 6's subtle fills are stronger than the panel's: the plain success and danger inks read 4.2–4.4:1 in light. Now at least 5.9:1.
- No `--pg-*` token changed; the bridge values outside `.badge` are as before.

## How does the status pill (`.pg-pill`) read?
- Its text is `color-mix(in srgb, var(--pg-tone) 70%, var(--pg-fg-emphasis))`; the bare tone on its tint was 3.0–4.4:1 in light (teal, amber, rose, gray, blue). Now at least 5.4:1 on the page and on a card. The dot keeps the plain tone.

## What was swept?
- Every badge kind × tone in Chromium, both themes: filled `theme-*`/`bg-*` (5.7–12.1:1, near-black `--pg-on-accent`), outline, `fg-*` on clear, `text-bg-*`, `pg-count`, `theme-inverse`. All 4.5:1 or more after the change.
- Real screens, both themes: orders, products, users, submitted forms, sessions, contacts, offers, comments, key codes, shipping methods, ERP, site log, API, gift cards, order and visitor reports, edit user, contact groups, campaign profiles, commissions, themes, Workspace. No pill under 4.5:1.
- `fg-black` on a badge appears only on warning fills (12:1). The visual editor's `--sd-*` chips are kept by `tests/designer_chrome_contrast_test.php`.

## What keeps it?
- `tests/panel_pill_contrast_test.php` reads tokens, tints and mix percentages from `backend.src.css`, computes every ratio, checks that the floor is unlayered and in `backend.min.css`, and refuses an opacity on a badge or pill rule.

Related: docs/memory/ui/panel-content-contrast.md, docs/memory/ui/dashboard-pill-contrast.md
