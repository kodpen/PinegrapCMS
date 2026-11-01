---
name: workspace-flows-visual-spec
description: The Flows canvas look in numbers (owner, 2026-10-27): lines, type colours and tones, thread sag, icons, run flags, mini window, template motion; all on screen (#4386)
---

## How are lines drawn?
- Control arrows: 2 px, `#7d8696`, from a box's right edge, filled head.
- Data threads: 1 px, no head, from the output dot on the source box's bottom edge to the bottom of the field chip they fill. A quadratic curve whose control point sits below the chord's middle: sag = 10 px + 0.06 × horizontal distance (0.05 in a mini window), so a longer span hangs a little more.
- Two threads never overlap; at least 3 px stays between them. Threads from one dot leave it a few px apart; the one going farther starts on the left and sags more, so they never cross near the dot.
- The selected box's threads at full colour, the rest dimmed; a "Data threads" toggle hides them all.

## Which colours?
- Types: number `#6ea8ff`, text `#e9b44c`, yes/no `#4cc38a`, person `#b49cff`, date `#c3c9d3`, address `#ff9aa0` (no thread may end in an address field; the one exception, #3410: a number thread into a webhook URL's path or query).
- Tones: threads of one type stay a few tones apart, even from the same dot: numbers `#6ea8ff`, `#9cc4ff`, `#4d8fe8`; texts `#e9b44c`, `#f5cf7d`, `#f0c060`. A field chip takes its thread's tone.
- Box kind labels: start `#6fdca6`, system `#45c4d6`, logic `#e9b44c`, message `#6ea8ff`, people and tasks `#b49cff`, value boxes `#e58fd0` with a dashed border. Selection ring `#a78bfa`.

## Which icons? (16 px, 1.5 px stroke)
- Boxes: manual start play, time start clock, event bolt, join person, web check globe, condition fork, message speech bubble, e-mail envelope, task check square, count summary bar chart, webhook three linked dots, approval shield with a tick, record change pencil, constants tag, derived value sigma.
- Output dots (#4060, PR #4189): a 14 px ring in the type colour with an 8 px type icon inside (`#` number, `T` text; person, calendar, toggle for the others) and the output's label beside it; the row 14 px high. An invisible 24 × 24 px target (`.wsf-data-ring::before`) takes the pointer, touch long-press included.
- Arrow ports (#4060, PR #4189): 10 px, 12 px on hover (`scale(1.2)`), 1.5 px ring in the page colour, centred 2 px outside the box's edge; an invisible 24 × 24 px target (`.wsf-port::before`).
- Mini window (boxes 128 × 60): no ports; thread dots r 2.5 (5 px), already under the canvas ratio (14 / 208 × 128 ≈ 8.6 px), kept.

## How do run flags look?
- Completed: green ground `#173a2a`, text `#6fdca6`, a flag icon, the state, the duration, one line of result; ▾ opens the whole result and a link to the run.
- Waiting amber (`#2a2416`, `#f3dca0`); Running blue (`#1b2638`, `#b9d3ff`) with Stop; Failed red (`#2a1d21`, `#ffb3b8`).
- Shown above the flow's last box on the canvas, in the list's last-run column and on the run's card in a channel.

## Mini window and templates?
- The AI draft card and Add › Scheduled flow draw the same canvas small (boxes 128 × 60 px): the dry run's result is each box's third line; clicking a box opens "Edit here" under it (on a waiting card it opens the draft first, #3444); "Open on canvas" opens the same draft.
- Template placement: boxes appear one by one (420 ms each, 150 ms apart, rising and scaling up from 92 %), arrows grow (200 ms), threads draw (650 ms, 200 ms apart), then the setup panel slides in. With prefers-reduced-motion it is placed at once.

## Where are the rules behind it?
- workspace-flows-thread-drawing.md, -box-look.md, -list.md, -canvas.md, -run-flag.md, -template-setup.md; workspace-flow-draft-card-window.md.

## Is everything in it on screen?
- Yes (#4386): run flags, template motion, the mini window, threads and colours match the code.
- Yes/no, person, date and address colours are not drawn: outputs are only number or text (`ws_step_output_types()`). A new output type takes its colour, tones and ring icon from here (`THREAD_TONES`, `.wsf-tone-*`, `.ws-fd-tone-*`).
