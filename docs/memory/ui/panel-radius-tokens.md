---
name: panel-radius-tokens
description: B10 radius (#4867): fields and buttons on Bootstrap 6's control radii, cards, dialogs and menus on --pg-radius-surface (radius-7), inner card corners from the card radius
---

## Which radius does a panel element take (B10 radius, #4867)?
- Fields and buttons: Bootstrap 6's own `--bs-btn-input-*-border-radius` (radius-5, 0.5rem; large radius-7). The bridge no longer sets them and no panel rule sets `--bs-btn-border-radius` or `--bs-pagination-border-radius` for all buttons. Before, fields kept v5's 0.375rem (small 0.25rem) beside 0.5rem buttons and input-group text.
- Cards, dialogs, menus (the header user menu included, which had its own 1rem): `--pg-radius-surface`, Bootstrap 6's radius-7 (0.75rem), one step rounder than the controls inside them.
- Panel-owned controls and rows (`.pg-filter`, `.pg-row`, menu items, `.input-group-text`) keep `--pg-radius-lg` (0.5rem), equal to the control radius. The `--pg-radius-*` scale did not change.

## What about inner corners?
- `--bs-card-inner-border-radius` is always `calc(var(--bs-card-border-radius) - var(--pg-border-width))`. The dashboard widget card computed it from `--pg-radius-2xl` (2rem): header corners stuck out past a 0.5rem card.

## What is pinned?
- `tests/bs6_radius_tokens_test.php`: no control radius override, the surface token on card, dialog and menu, inner radii from the card radius.

## What is left?
- The Workspace's own `--ws-radius-*` scale (0.45/0.65/1/1.25rem) and literal radii in backend.src.css (`.25rem`, `3px`, `999px` and the like) were not moved onto the tokens.
