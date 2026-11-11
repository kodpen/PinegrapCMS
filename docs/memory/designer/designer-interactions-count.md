---
name: designer-interactions-count
description: The count interaction (Count up): fields, how pg_fx.src.js reads the first number and counts it, reduced motion, the canvas, the panel, tests.
module: designer_fx
---

## What is Count up?
- `_fx` effect `count` (B3 #8728): the first number in a text node's text counts up from zero once the node is seen. The markup holds the real number, so a page without the script, a search engine and a screen reader read it as it is.
- Fields: `duration` (100–5000 ms, own default 2000, `pg_fx_count_numbers()`, `_sdFxSpec().count`) and `delay` (0–5000, default 0). No trigger, so the head style never hides the node; trigger, easing, stagger, repeat and the others drop out. `pg_fx_attrs()` writes the duration only when it is not 2000.

## How does the script find the number?
- `pgFx.number(text)`: the first run of digits with `.` `,` no-break space, narrow no-break space, `'` or `’` between them. Two kinds of mark: the last one is the decimal mark. One kind more than once, a space or an apostrophe: grouping. One `.` or `,` once: grouping when three digits follow and one to three digits but a lone 0 come before (10.000, 12,500), else a decimal mark (4.9, 0.125, 1234.567).
- Groups are three digits, or two above the last three (Indian 12,50,000). Separators that make no number (1.2.3, 1,234.5,6) give none, and the node does not count. A leading zero keeps the width (007).
- The text before and after stays (`%99,9`, `$1,234.56`, `10.000+`, `Since 2010`); only the first number counts (`24/7` counts the 24).

## How does it count?
- At bind the number is written as zero, so the first frame never shows it whole; IntersectionObserver (threshold 0) starts the count once, then unobserves. Without IntersectionObserver it counts at once.
- `pgFx.countText(n, p)`: the value eased out (cubic) at p of the duration, written with the number's separators and decimals; at the end the text node gets the markup's text back verbatim. One write per frame, from the first text node under the node that holds a number (script, style and template skipped).
- Reduced motion: nothing is written, the number shows. Switched on while counting: `still()` puts the number back and a run token stops the queued frames.

## What does the editor do?
- The Effect list offers Count up on content nodes of type heading, paragraph, span, text and link, and keeps it on any node that has it. Rows: Duration, Delay, a note on what it counts, and Play on the canvas. Switching to or from Count up resets the duration to the new effect's default.
- On the canvas (`pgFxEditing`) a press or focus on a counting number shows it at once, so an edit never takes a half-counted text. A node drawn again by a later render shows its number; Play counts it again (`pgFx.replay()`), whatever the motion setting.

## How is it tested?
- `tests/designer_fx_test.php`: `test_designer_fx_count` (normalizer, attributes, twins), `test_designer_fx_count_numbers` (number formats under Node), `test_designer_fx_script_counts` (the count on a fake page: zero until seen, frames, end text, reduced motion, canvas press and Play), the panel rows in `test_designer_fx_panel`.

Related: docs/memory/designer/designer-interactions.md, docs/memory/designer/designer-interactions-script.md
