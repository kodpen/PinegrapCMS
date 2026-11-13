---
name: legacy-bootstrap-helper
description: Plan: the helper that turns a Legacy design towards Bootstrap 5.3.8 and a design theme (#8856): a trial design beside it, what it changes, how it is seen and kept, what stays reversible
---

## What is asked?
- Owner (2026-11-11, #8671, `workflow/decisions-made-product-43.md`): "Bootstrap 5.3.8'e geçiş yardımcısı", the last of the four parts; the other three are built (LG1-LG7, `designer/legacy-overview.md`). The plan was asked for on #8856.
- A Legacy design carries a classic theme: Bootstrap 3 markup, the theme's CSS and jQuery scripts, the classic head's frame as shared parts. The helper turns it towards a Bootstrap 5.3.8 design that wears a look and a palette.
- "Towards" is read by the architect as: a start a designer finishes in the editor, not a pixel copy. LB1 measures how near it gets.

## Why a new design, never the Legacy one changed in place?
- A design's framework is written once, when its row is made, because its pages are written against it (`designer/design-framework.md`). So the helper makes a trial design beside the Legacy one: a new row, framework `bootstrap5`, name "<name> (Bootstrap 5)" (`get_unique_name()`), with converted copies of the shared parts. No page uses it until a designer moves pages.
- The trial design names its Theme file itself (`style.theme_id`, the activated theme the Legacy design loaded): only a `legacy` design gets that theme unasked (`designer/legacy-design-theme.md`).

## What does the helper change?
- Markup, in the parts and in each page's tree: Bootstrap 3 classes and data attributes to their 5.3.8 names by one map (grid, offsets, visibility, floats, text sides, buttons, images, panels to cards, labels to badges, form groups, `data-toggle` to `data-bs-toggle`, `sr-only`); Glyphicons and Themify icons to Bootstrap Icons where a twin exists. A class the map does not know stays and is counted.
- CSS: the classic framework sheet is left out, the framework brings 5.3.8. The theme's other sheets stay; a sheet whose selectors name a renamed class gets a copy of the trial design's own. The Legacy design's files are never rewritten.
- Theme parts (LB6): a part with a twin in the designer becomes it (slider, photos in `.background-image-holder`, lightbox, the Bootstrap 3 navbar) and its script row is left out. The rest stay with their scripts. jQuery stays.
- Theme (LB7): the theme's colours and fonts are read into a palette and a look (`file-<id>`, `designer/design-theme-look-and-palette.md`) the trial design can wear.

## How does a designer run it and see the result?
- Move to the Page Design Studio (`legacy_to_designer.php`), a card "Towards Bootstrap 5" listing each Legacy design: Report (converted, kept, by hand; nothing written), then "Make a trial design".
- Each page of the Legacy design opens side by side, Legacy and trial, wide and phone: the page's tree is converted for the preview only, nothing saved.
- "Move pages": all or the chosen ones. Designers only (role 0 or 1), not while a move runs.

## What stays reversible?
- Nothing is removed (#6006, #6251): the Legacy design, its parts and files and the classic styles stay. The trial design can be deleted while no page uses it.
- A page's Legacy tree is written as its newest revision before the converted tree is saved (`pg_page_revision_record()`, as Back to classic does, `designer/legacy-to-classic.md`); the run's kept report holds each page's origin design. "Send back to Legacy" gives the page its tree and its design back. No schema.

## Which questions wait for the owner?
- The three after LB1 are answered (2026-11-12, #8942): `legacy-bootstrap-helper-rules.md`. Next: what the helper does on a design that is not Bootstrap, after LB1b's pictures.

## Where are the steps?
- `legacy-bootstrap-helper-steps.md`, with the rules every step keeps.
