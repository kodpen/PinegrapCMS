---
name: decisions-made-product-17
description: Decisions list, product part 17 (2026-11-01 midday on): WebP at a picture's own address (default, convert doors, master file); the new dashboard's storage, visitor map, daily summary, role lock, gauge
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## WebP at a picture's own address: default, convert doors, editor export (2026-11-01)
- Owner (2026-11-01) delegated the six questions of `plans/webp-same-address.md` to the architect. Decided: a new install has it on, a running site off until switched on (the upgrade note and System Status say so); the File Manager's batch becomes "Prepare WebP" and Edit File keeps "Create a .webp copy", no door renames a file; the image editor saves another format as a copy, Replace never changes a file's format; the switches are bits 4 and 8 of `config.image_derivative_formats`, no schema; AVIF at the plain address not now; Cloudflare Tools writes and checks the Cache Rule when its token may. Reason: a file's bytes match its name's extension, and an upgrade starts no encodes on a shared host.
- Owner (2026-11-01, #4677): the uploaded file stays the master, never the WebP copy; a setting shrinks pictures above N px on upload (same format), 2560 px on a new install, off on a running site; the product ceiling stays its own. The 320 px rung and the Workspace previews moving to the derivative cache are the architect's (W7, W8).

## The new dashboard: storage, visitor map, daily summary, role default and lock (2026-11-01)
- Owner (2026-11-01, PR #4669, the four questions of `plans/new-dashboard.md`): (1) one new table `dashboard_layout` (step `11.0.141`) holds the switch, the person's layout and look, the sign-in times, the role defaults and the lock; (2) a site behind Cloudflare takes the visitor's city from `CF-IPCity` and writes it on the visit, otherwise the visitor map draws at country level, no schema; (3) the assistant card's daily summary is a setting with an hour, off by default, since each run spends one of the admin's daily Claude runs; (4) a role default reaches people who never saved a layout, and a lock shows everyone in the role the default and blocks editing while their own layouts are kept for when it lifts. The current dashboard and its `dashboard` row stay untouched.
- Owner (2026-11-01, #4726): the new dashboard's System Status card draws the current gauge of widget 2 (`.pg-health`: the half arc in the score's colour band, the pixel matrix, glow and readout), not the design's dot half ring; N3 moves it into one function both dashboards call, widget 2's output unchanged. Reason: three days of work went into that gauge.
