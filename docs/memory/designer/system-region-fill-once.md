---
name: system-region-fill-once
description: A system widget placed twice on a classic page (side column and footer): each <system> tag is filled by its own render, so both copies get their own inline editing ids in the site's Edit mode
module: content
---

## How does a page fill its <system> tags?
- `get_page_content()` visits every `<system>name</system>` tag of the layout in order (the same name twice included) and renders each one; `pg_system_region_fill($content, $name, $output)` (`includes/fn/content.php`) then fills the first tag of that name still in the content (any case, the name as text, the output as text).
- A missing page (deleted, or binned: the Recycle Bin parks its name) fills its tag with nothing, one tag at a time too.

## What was the symptom?
- #9129: the fill was a `preg_replace()` without a limit, so the first render filled both tags. In Edit mode the sample `/blog` page showed `blog-widget` twice with `software_inline_editing_region_1` and `_2` on both copies; the second render's ids (7 and 8) were dropped. The footer copy had no editor, the console logged `editor-element-conflict` four times, and the region list held the same regions twice.
- The second render already ran before (its counter moved on); only its output was thrown away. Visitors now see the second render in the second place, which for every widget seen draws the same as the first.

## What is left?
- Both copies of a widget's System Region Header or Footer save to the same page; when both are changed in one save, the one posted last wins.

Tests: `tests/system_region_fill_test.php`.
