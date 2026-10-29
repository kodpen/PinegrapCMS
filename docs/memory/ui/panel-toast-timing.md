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
