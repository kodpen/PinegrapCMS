---
name: workspace-channel-look-prototype
description: The style sheet tried on dev for step CL1 of plans/workspace-channel-look.md (one header card, colour wash, pill tabs, day chip, writing box card); a browser trial, not in the code
---

## What is this?
- The style sheet put into the Workspace channel screen on dev (2026-11-10) to make the proposal's picture. Nothing of it is in the code: CL1 writes it properly into the Workspace block of `backend.src.css`.
- `data-proto-colored` was set by hand on `.ws-center` for a channel that has a colour; the real screen knows that from `--ws-ch`.

## What was put into the page?
```css
@layer custom {
  /* The header and its tabs are one card; the channel's colour is a soft wash
     from the side its sign stands on, no stripe. */
  .ws-center > .ws-head.ws-head-channel {
    margin: .75rem .75rem 0;
    padding: .7rem 1rem .55rem;
    border: 1px solid var(--pg-border-color);
    border-bottom: 0;
    border-radius: .875rem .875rem 0 0;
    background: var(--pg-card-bg, var(--pg-bg-1));
    box-shadow: none;
  }
  .ws-center > .ws-head.ws-head-channel + .ws-tabs {
    margin: 0 .75rem;
    padding: 0 1rem .6rem;
    border: 1px solid var(--pg-border-color);
    border-top: 0;
    border-radius: 0 0 .875rem .875rem;
    background: var(--pg-card-bg, var(--pg-bg-1));
    gap: .3rem;
  }
  /* Both halves of a coloured channel's card. */
  .ws-center[data-proto-colored] > .ws-head.ws-head-channel,
  .ws-center[data-proto-colored] > .ws-head.ws-head-channel + .ws-tabs {
    border-color: color-mix(in srgb, var(--ws-ch) 26%, var(--pg-border-color));
    background:
      linear-gradient(100deg, color-mix(in srgb, var(--ws-ch) 15%, transparent) 0%, color-mix(in srgb, var(--ws-ch) 5%, transparent) 38%, transparent 70%),
      var(--pg-card-bg, var(--pg-bg-1));
  }

  /* Tabs as quiet pills; the open one is filled. */
  .ws-center > .ws-head.ws-head-channel + .ws-tabs button {
    padding: .3rem .7rem;
    border: 1px solid transparent;
    border-radius: .6rem;
    font-size: .8125rem;
    line-height: 1.4;
  }
  .ws-center > .ws-head.ws-head-channel + .ws-tabs button:hover {
    background: var(--pg-bg-2);
  }
  .ws-center > .ws-head.ws-head-channel + .ws-tabs button.active {
    border-color: rgba(var(--ws-accent-rgb), .35);
    background: rgba(var(--ws-accent-rgb), .14);
    color: var(--pg-fg-emphasis);
  }
  .ws-center[data-proto-colored] > .ws-head.ws-head-channel + .ws-tabs button.active {
    border-color: color-mix(in srgb, var(--ws-ch) 45%, transparent);
    background: color-mix(in srgb, var(--ws-ch) 18%, transparent);
  }

  /* The day mark is a chip, not a rule across the pane. */
  .ws-day::before,
  .ws-day::after {
    opacity: 0;
  }
  .ws-day > span,
  .ws-day {
    justify-content: center;
  }

  /* The writing box as a card. */
  .ws-composer {
    border-radius: .875rem;
    border-color: var(--pg-border-color);
    background: var(--pg-card-bg, var(--pg-bg-1));
  }
}
```
