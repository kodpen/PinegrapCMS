---
name: panel-toast-timing
description: pgToast() timing: danger and warning toasts stay until closed and are not stacked twice; success and info keep their timer
---

## How long does a pgToast() stay?
- `success` and `info`: `opts.delay` ms (default 4000), then they hide.
- `danger` and `warning`: until their Close button is used (`autohide: false`); `opts.delay` is ignored for them. The no-`pgUI` fallback follows the same rule (no timer, Close removes it).

## Why?
- A save or create failure in the corner (inline cell edit, offer save, product attribute create, counter sale with its forced 3.5 s) was gone before a keyboard or magnifier user could reach it, often with nothing else on screen saying what failed. Bootstrap 6's Toast pauses only while the pointer or focus is already on it (WCAG 2.2.1).

## Does a repeated error stack up?
- No: a `danger` or `warning` toast with the same variant and text already in `#pg-toast-container` (`data-pg-toast-key`) is not added again; the call returns that toast's instance.

## Do callers need to change?
- No caller sets anything: the rule sits in `pgToast()` (`backend.src.js`). `tests/panel_timers_test.php` pins it.

## Where does it sit?
- On the body under the panel's top bar, `#pg-toast-container` starts at `top: var(--pg-navbar-h)` with z-index 1025: under the bar (1030), above sticky bars (99, 1020) and the DataTables floating header (3). `placeToastContainer()` sets it on every `pgToast()`, after `pgUI.hostLayer()`.
- Why: at `top-0` and 1090 a lasting error toast covered the bell, the account button and the header menus they open (they sit in the bar's stacking context, not the top layer).
- Inside an open dialog (moved there by `hostLayer()`) or on a screen without the bar it keeps `top-0` and 1090.
- The open chat panel (1080) covers a toast where they overlap, as it covers the header menus. `tests/panel_toast_place_test.php` pins this.
