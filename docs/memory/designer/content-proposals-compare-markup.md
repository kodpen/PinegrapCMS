---
name: content-proposals-compare-markup
description: Compare on Waiting Content Changes when a text's HTML changes (link address, bold, style), alone or with its words, or past the 600-character cut: differing values never print the same
module: content_proposals_compare
---

## What does Compare show for a change only in the markup?
- (#10215) A value is printed as its words, tags stripped. When both sides' words are equal but their trimmed HTML is not, the stripped words would print the same twice and a designer would apply a new link address unseen (the page prints `props.text` raw).
- Such a line says "Only the markup changed: links, formatting or styles." under its kind and prints each side's HTML whole (not cut at 600 characters), escaped with `h()`, in a `<code>` block (`bg-1 border rounded p-2 small font-monospace text-break`).
- Words and tags changed together (the lists of `<…>` with attributes differ): the words as before, each side's HTML in the code block under them, and "The markup changed too: links, formatting or styles." Otherwise one changed letter would carry a new link address past the designer (architect, 2026-11-17, agent channel message 8436).
- Words that differ keep the plain look, cut at 600 characters (`PG_CONTENT_PROPOSALS_COMPARE_WIDTH`), unless the cut prints both sides the same: then both are printed whole (`pg_content_proposals_compare_width()`). A picture line never takes the code block.
- The rule: for two values that differ, the two printed sides differ, and a changed address is readable on its side.

## Where is the code?
- `includes/fn/content_proposals_compare.php`, loaded by `content_changes.php` only: `pg_content_proposals_compare_line()` makes one line, `pg_content_proposals_compare_kind()` decides (plain, markup, both; `pg_content_proposals_compare_tags()` lists the tags), `pg_content_proposals_compare_value()` prints one side, `pg_content_proposals_compare_words()` strips. It was moved out of the screen's closures so it can be tested.
- It applies to every compared value (text, note, page settings); `area` and `tree` lines print fixed words.

## Which test guards it?
- `tests/content_proposals_compare_test.php`: a link address changed inside the same words, words and link changed together, words that differ only past the cut, a table of pairs that must print differently, bold only, outer spaces only, `<script>` and `<img onerror>` escaped, a change past 600 characters still shown.
