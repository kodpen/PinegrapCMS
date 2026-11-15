---
name: auto-dialog-popups
description: Plan (owner 2026-11-15, poll 51 and agent channel message 5139): pop-ups built by growing Auto Dialogs: the browser's own dialog in place of jQuery UI, new triggers, designed content, counts
priority: 6
---

## What is asked?
- Owner (2026-11-15, agent channel message 5096): "tasarım stüdyosunda popup tarzı bir component olmadığını fark ettim bu şekilde birkaç popup comp. i de eklemeyi düşünelim." His vote on poll 51: "Evet, ikisi de".
- Owner (5139): pop-ups are built by growing Auto Dialogs, new columns where needed, the jQuery dialog gone, the screens improved. His words are in `workflow/decisions-made-product-56.md`.
- The shape is the R&D agent's (agent channel message 5160) with the architect's changes (5179, corrected in 5215).

## What is the shape?
- The frame: the browser's `<dialog>` opened by a small script: `showModal()` for a centred pop-up, `show()` for a bar or a corner card; no library. Classes `auto_dialog` and `auto_dialog_<id>` stay, the `ui-dialog` classes go. jQuery UI stays for the edit-region, share-comment and kiosk dialogs.
- An old row does what it does today: its address in an iframe, centred, its size, delay, pages, frequency, the two `disable_auto_dialog` body classes, the staff preview. Its frame is drawn to match today's.
- `software.open_dialog()` stays as a thin wrapper on the new dialog: a site's own script may call it.
- The pick moves to the browser: the server prints the page's enabled rows the same for everyone; the script reads the cookie and counts the delay from the visit's first page (`sessionStorage`). No session key, so a site with an auto dialog is cached again (`platform/page-cache-gate.md`).
- New columns, each default reading as today: `open_on` (delay, scroll, leave; on a touch screen leave falls back to the delay), `open_scroll` (1 to 100), `audience` (all, cart), `content` (url, component), `component_id`, `place` (centre, top, bottom, corner). A cart row is printed only when the session holds an order with a line; such a session gets no kept copy.
- Designed content: a shared component designed alone (`designer/shared-component-opened-alone.md`), drawn once into a `<template>` at the body's end and moved into the dialog: no iframe, so its form, bindings and offer code work as on a page.
- Four ready blocks in a family file of their own, group Pop-ups: offer code with Copy, newsletter sign-up, announcement bar, cart waiting. Each marks its close button and its main action (`data-pg-popup-close`, `data-pg-popup-take`).
- Cookie: one for all pop-ups (id, last shown, state; a year), written only with the notice off or marketing allowed, in the catalogue and the revoke names; otherwise `sessionStorage`, once a visit. The old per-row cookie is read for its last-shown time and joins the revoke names.
- Counts: one row per pop-up and site day (shown, closed, taken); a beacon to `page_view.php` once per pop-up, event and page load, none for staff, crawlers or a preview. Taken = a press on the marked action or a form sent inside; an address kind knows shown and closed only.

## Does a pop-up wait for the cookie notice's answer? (owner, 2026-11-15, agent channel poll 51)
- Yes: with the notice on, no pop-up opens before the visitor has answered it; the script starts on the `pg:consent` event. With the notice off it opens on its trigger. Old rows too.
- His vote and the outcome line: `workflow/decisions-made-product-56.md`.

## What does every step keep?
- Schema only through a step that states its columns, types and indexes in its draft PR's body; the architect confirms them there before the rest is written (`workflow/decisions-made-schema-21.md`).
- Looks: the old row's before and after (PU2) is the architect's look. The three screens (PU5) and the four blocks (PU7) are new looks: close-up pairs go to the owner, and neither step is ready before his look.
- Steps: `plans/auto-dialog-popups-steps.md`. Claim as `[plan:auto-dialog-popups] <step> — <topic>`.
