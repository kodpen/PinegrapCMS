---
name: agent-drawing-guides-census
description: DG1 census and proposal: what today's agent charts lack, and the guide's proposed boards, grid, type, spacing, two board palettes, marks and seven chart kinds
---

## What do today's agent charts lack?
- Pictures: `plans/agent-drawing-guides/today-*.jpg` (source `today.html`): the CO8 drawing (hand SVG), Chart.js on a live page at its defaults, a typical hand SVG line chart (redrawn).
- No axis, values or units; no font given (serif fallback) or Arial at 12 px, read at 9 px once the card shows a 1200 board at half size; colours not the panel's and no second set for dark; dashed grids and a number on every point; Chart.js ignores the panel theme.

## Which boards and grid?
- Presets: Card 1200 × 675 (default), Slide 1920 × 1080, Square 1080 × 1080, Portrait 1080 × 1350, Story 1080 × 1920, Figure tile 600 × 360, Sparkline strip 600 × 120.
- Scale = shorter side / 675; every pixel value below is multiplied by it (tile and strip keep their own values).
- 12 columns, margin 48, gutter 24, unit 8, value axis gutter 64. Left aligned: title, subtitle, legend, plot, source note at the bottom; no centred title.

## Which type?
- One family, Inter (`g:Inter`, else `sans`). The editor snaps weights to 300/400/600/700/900, so the panel's 550/650 become 600/700.
- Title 44/700, big figure 72/700, subtitle 24/400, label/legend/value 20/600, axis 18/400, note 16/400. Nothing under 16 on a Card board.
- Spacing: multiples of 8; title to subtitle 8, subtitle to legend 24, legend to plot 40, plot to note 24.

## Which colours?
- Dark board: surface #14161c, ink #eef0f3 / #a4a9b1 / #6d737c, grid #262930, baseline #3a3e47; series #5892ea, #00ac86, #9580e0, #be8700, #d46d83, other #5d636c; up #3fd0a8, down #f58aa0.
- Light board: surface #ffffff, ink #12151a / #58606a / #8a919b, grid #eceef1, baseline #c9cdd3; series #2f6fed, #0e9a74, #7150e6, #b7791f, #d43d6b, other #a3a9b1; up #0e9a74, down #d43d6b.
- Series are the panel tones (blue, teal, violet, amber, rose) in fixed order; the dark set is the same hues at OKLCH L 0.66 (the panel's dark tones were too light for marks). Both sets pass the dataviz validator: lightness band, chroma, adjacent CVD ΔE >= 10, contrast >= 3:1.
- Text never wears a series colour; up/down only on a change figure, always with an arrow. At most five series, the rest folds into "Other".

## Which marks?
- Bar at most 40 thick and 56 % of its band, 6 radius on the data end, square at the baseline; 4 between grouped bars, 2 surface gap in a stack, no outlines.
- Line 3, round joins; end dot r 6 with a 3 surface ring and the last value beside it. Area: the series mixed 14 % into the surface as a solid colour, so the layer stays editable.
- Donut: outer 190, inner 128, total in the middle, legend at the right with count and share.
- Grid horizontal only, 1 thick, solid; baseline 2; 4-5 round steps. Values labelled selectively (the highlighted bar, line ends). Legend from two series up. Numbers in Turkish format (31.200, 31,2 B, 12,4 %, ₺ first).

## Which chart kinds?
- Bar, stacked bar, line, area, donut, sparkline, figure tile: `plans/agent-drawing-guides/kinds-*.jpg`; the guide sheet `guide-*.jpg`. Sources `kinds.html`, `guide.html` with the model drawing code in `guide.js` (the values above as `G`): DG3's server writer can follow it.

## What was not checked?
- Whether the server's Imagick drawing has Inter (a browser loads it from Google); whether every element of the drawings splits into editable layers. Both are DG3's tests.
