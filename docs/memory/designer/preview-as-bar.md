---
name: preview-as-bar
description: The "Preview as" bar in the Preview window (PW3): the part previewas, the core hooks, the choice kept per page, what each chooser sends, the lines under the bar, the narrow sheet
module: designer
---

## Where is it built?
- Part `previewas` (`includes/designer_parts/previewas.php`, `assets/js/style_designer_previewas.js`, `when` always), loaded the first time a `preview_widgets` answer says the page offers a choice. The core keeps section `// ── Preview as ──` (`_sdPvAs*`) before the live preview's.
- The bar lives in the Preview's blob document but runs from the editor's window (same origin): an open menu, the search typed and the spinner survive the live preview's reloads. `_sdShowPreview()` puts the bar into the markup (`inject`) and binds the new document (`attach`); `_sdPreviewSent` stays the page's own markup, so an unchanged page is still not reloaded.
- The page is pushed down by the bar's height (`--pg-pva-h` on `html`, padding-top); Bootstrap's `.fixed-top` and `.sticky-top` move under it. "Hotspots" stays at the lower left.

## When does it show?
- From the answer's `setups` (sid => {case, record, source, cases}, `pg_designer_preview_widgets()`): "Record" when a widget has a record source, "Case" when the widgets have more than "Typical", "Visitor" when the page's tree has an audience condition (`_sdPvAsAudience()`). None: no bar, no part loaded, the Preview as before.
- Access rules are not known to the editor yet: `page.access_rules` is sent false and "Member of a group" is not drawn.

## What does the choice send?
- Kept per design and page in localStorage (`pgPreviewAs:<design>:p<page id>`, the tab key before the first save; removed when it is the default): `{case, who, visit, records: {sid: id}, labels}`. Nothing is saved with the design.
- `_sdPvAsApply()` adds `preview_as` `{case, visitor, page: {access_rules, audience}}` and each widget row's own `record` (read by `pg_designer_preview_override_rows()`, over the page's). The server takes one visitor: signed in wins over the visit.
- A choice redraws at once (`_sdPvAsChoose()`); the drawn page stays until the answer, the bar shows "Drawing…". A failed draw keeps the last page and shows the rose line with "Try again".

## What does the "Record" menu list?
- `designer/preview_records` rows `{id, label}` (at most 20, the operator's own rights); no total count and no price or date detail, so the foot reads "Type to find the others." when 20 came. The default record is the one drawn with no choice (`setups[sid].record`), marked "shown by default"; choosing it clears the choice.
- A kept record that came back as another one (gone, or no longer allowed) is forgotten and the amber line names it. "Long" and "Empty" dim the chooser.

## What does "Edit content here" add to the bar?
- Its switch or link last in the bar's right end and as a row of the narrow sheet (`K._sdRecEditHtml()`), the document handed over on each `render()` (`K._sdRecEditSync()`), and `note()`/`redraw()` for its line (`record-edit-in-place-preview.md`).

## What is left for later steps?
- "Check all cases" (PW4), the "Member of a group" rows (with access rules), and signed-in drawing (PW2b to PW2d read the visitor).
