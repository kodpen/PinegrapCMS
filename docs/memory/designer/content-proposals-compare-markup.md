---
name: content-proposals-compare-markup
description: Compare on Waiting Content Changes for a text or note whose words stay the same and whose HTML changes (a link's address, bold, a styled span): both sides' HTML shown escaped as code
module: content_proposals_compare
---

## What does Compare show for a change only in the markup?
- (#10215) A value is printed as its words, tags stripped. When both sides' words are equal but their trimmed HTML is not, the stripped words would print the same twice and a designer would apply a new link address unseen (the page prints `props.text` raw).
- Such a line says "Only the markup changed: links, formatting or styles." under its kind and prints each side's HTML whole (not cut at 600 characters), escaped with `h()`, in a `<code>` block (`bg-1 border rounded p-2 small font-monospace text-break`).
- A line whose words differ keeps the plain look: the stripped words, cut at 600 characters (`PG_CONTENT_PROPOSALS_COMPARE_WIDTH`). A picture line never takes the code block.

## Where is the code?
- `includes/fn/content_proposals_compare.php`, loaded by `content_changes.php` only: `pg_content_proposals_compare_line()` makes one line, `pg_content_proposals_compare_markup_only()` decides, `pg_content_proposals_compare_value()` prints one side, `pg_content_proposals_compare_words()` strips. It was moved out of the screen's closures so it can be tested.
- It applies to every compared value (text, note, page settings); `area` and `tree` lines print fixed words.

## Which test guards it?
- `tests/content_proposals_compare_test.php`: a link address changed inside the same words, bold only, words that differ, outer spaces only, `<script>` and `<img onerror>` escaped, a change past 600 characters still shown.
