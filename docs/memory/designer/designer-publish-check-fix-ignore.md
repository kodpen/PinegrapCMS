---
name: designer-publish-check-fix-ignore
description: The Publish check's "Fix", "Show me" and "Ignore on this page" (PC5, #11607): which findings get Fix, the undo step, where the reason is kept, the ignored fold, what is not counted
---

## Which button does a finding get?
- One, at the end of its card: "Fix" where one press puts it right with no choice, else "Show me"; a check that failed has none (`PgPublishFindings.action(f, sizes)`).
- Fix: an empty heading (`audit:heading_empty`, removed), a picture without width and height (`weight:img_no_size`) when the measure knows its file's pixel size and it is an Image element whose Width and Height are both empty (a typed size is a choice), a left-over component value (`component:field_leftover`, no source gives it yet). Never inside a shared component: its tree is every page's.
- "Show me" closes the sheet and selects the element as the path does (its options come with the selection); a finding of the page opens Page Settings of its tab. The line over the canvas with "Back to Publish" follows both.

## What does "Fix" do?
- `_sdPageEdit(key, fn)` (core, in the part kit): the active page through `saveState()` and `render()`; another tab's page gets the step on its own `undoStack`. So it is one undo step and the same Publish saves it.
- The card turns into a result line: green check, "Fixed: …" and "Undo"; Undo runs the inverse as another page edit. The count and the row's result drop at once (`PgDesignerPublish.update(key, summary, focus)`, which also reads the rows again: the page may now be changed). The kept answer follows the new tree: no new check.

## Where is "Ignore on this page" kept?
- On the page tree's root, `props._pcIgnored` = { finding id: reason } (`PgPublishFindings.ignore()` / `ignored()`); the root draws nothing on the site or canvas, and the draft's tree is the draft's storage, so Publish or saving the page as a draft keeps it. No table or column.
- Only a finding to look at may be ignored (`canIgnore()`): a blocking one goes only with "Publish anyway". The reason is required, trimmed, at most 500 characters; an empty one ignores nothing. The finding id (`source:code:node:index`) is stable for the same element.
- An ignored finding is not counted (`split(found, ignored, fixed)`, then `summary(shown)`). It sits in a fold "Ignored on this page (n)" at the end of its group: dimmed, eye-slash, the path, "Ignored: “reason”", "Stop ignoring".
- `_pcIgnored` is left out of the page's check signature (`_pcSig()`), so an Ignore never runs the check again.

## How does the Weight group draw?
- `seo_check` with `weight: 1` (only the Publish check sends it) answers `weight` from `pg_designer_publish_weight()` (`designer-publish-weight.md`); null for a page not saved (the group says the check runs once saved), false when the draft did not render (one failed finding).
- Head: the count and "{n} in all". A card of four bars (Markup, Styles, Scripts, Pictures of the first screen): "262 KB of 200 KB", the budget's mark at two thirds, the grade's colour (green, amber up to twice, red), read as text by screen readers. Over-budget measures and the unused rules count as findings but have no card. Pictures and scripts are cards.
- "Rules no page of the design uses": a fold with each selector (and its media), "Listed for you to look at; nothing is removed by itself.", no button. Without bytes per rule, the head gives the count only.

Tests: `tests/designer_publish_findings_test.php`, `tests/designer_publish_check_test.php`, `tests/publish_weight_test.php`.
